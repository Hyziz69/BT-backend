<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountApprovedMail;
use App\Mail\AccountRejectedMail;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'users_count' => User::count(),
            'active_users_count' => User::where('status', 'active')->count(),
            'pending_users_count' => User::where('status', 'pending')->count(),
            'rejected_users_count' => User::where('status', 'suspended')->count(),

            'students_count' => User::where('account_type', 'student')->count(),
            'admins_count' => User::whereIn('account_type', ['nti_admin', 'superadmin'])->count(),
            'mentors_count' => User::where('account_type', 'mentor')->count(),

            'total_programs' => Program::count(),
            'active_programs' => Program::where('is_active', true)->count(),

            'total_calls' => Call::count(),
            'open_calls' => Call::where('status', 'open')->count(),
            'closed_calls' => Call::where('status', 'closed')->count(),
            'draft_calls' => Call::where('status', 'draft')->count(),

            'total_applications' => Application::count(),
            'approved_applications' => Application::where('status', 'approved')->count(),
            'rejected_applications' => Application::where('status', 'rejected')->count(),
            'pending_applications' => Application::whereNotIn('status', [
                'approved',
                'rejected',
                'completed',
                'archived',
            ])->count(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->select([
            'id',
            'first_name',
            'last_name',
            'email',
            'account_type',
            'status',
            'gdpr_consent',
            'created_at',
        ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json($users);
    }

    public function showUser(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => [
                'sometimes',
                'required',
                Rule::in([
                    'student',
                    'mentor',
                    'company_contact',
                    'editor',
                    'nti_admin',
                    'superadmin',
                ]),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'active',
                    'pending',
                    'suspended',
                ]),
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function approveUser(User $user): JsonResponse
    {
        if ($user->status === 'active') {
            return response()->json([
                'message' => 'User is already approved.',
                'mail_sent' => false,
                'user' => $this->userPayload($user->fresh()),
            ]);
        }

        $user->update([
            'status' => 'active',
        ]);

        $mailSent = $this->sendAccountApprovedMail($user->fresh());

        return response()->json([
            'message' => $mailSent
                ? 'User approved successfully. Email notification was sent.'
                : 'User approved successfully, but email notification was not sent.',
            'mail_sent' => $mailSent,
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function rejectUser(User $user): JsonResponse
    {
        if ($user->status === 'suspended') {
            return response()->json([
                'message' => 'User is already rejected.',
                'mail_sent' => false,
                'user' => $this->userPayload($user->fresh()),
            ]);
        }

        $user->update([
            'status' => 'suspended',
        ]);

        $mailSent = $this->sendAccountRejectedMail($user->fresh());

        return response()->json([
            'message' => $mailSent
                ? 'User rejected successfully. Email notification was sent.'
                : 'User rejected successfully, but email notification was not sent.',
            'mail_sent' => $mailSent,
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser instanceof User && $currentUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own admin account.',
            ], 422);
        }

        if (in_array($user->account_type, ['nti_admin', 'superadmin'], true) && $user->status === 'active') {
            $activeAdminCount = User::whereIn('account_type', ['nti_admin', 'superadmin'])
                ->where('status', 'active')
                ->count();

            if ($activeAdminCount <= 1) {
                return response()->json([
                    'message' => 'You cannot delete the last active admin account.',
                ], 422);
            }
        }

        $deletedUser = $this->userPayload($user);
        $deletedBy = $currentUser instanceof User ? $this->userPayload($currentUser) : null;
        $adminEmails = $this->getDeletionNotificationEmails($currentUser);

        try {
            $user->delete();
        } catch (QueryException $e) {
            Log::error('User deletion failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'This user cannot be deleted because related project data exists. Reject or suspend the user instead.',
            ], 409);
        }

        $mailSent = $this->sendAccountDeletedMail($deletedUser, $deletedBy, $adminEmails);

        return response()->json([
            'message' => $mailSent
                ? 'User deleted successfully. Admin notification was sent.'
                : 'User deleted successfully, but admin notification was not sent.',
            'mail_sent' => $mailSent,
            'mail_recipients' => $adminEmails->values(),
            'user' => $deletedUser,
        ]);
    }

    private function sendAccountApprovedMail(User $user): bool
    {
        try {
            $this->sendWithMailtrapThrottle(function () use ($user) {
                Mail::to($user->email)->send(new AccountApprovedMail($user));
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('AccountApprovedMail failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendAccountRejectedMail(User $user): bool
    {
        try {
            $this->sendWithMailtrapThrottle(function () use ($user) {
                Mail::to($user->email)->send(new AccountRejectedMail($user));
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('AccountRejectedMail failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getDeletionNotificationEmails($currentUser): Collection
    {
        if (!$currentUser instanceof User || !$currentUser->email) {
            return collect();
        }

        return collect([$currentUser->email])
            ->filter()
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    private function sendAccountDeletedMail(array $deletedUser, ?array $deletedBy, Collection $adminEmails): bool
    {
        try {
            if ($adminEmails->isEmpty()) {
                Log::warning('Account deletion mail was not sent: no admin recipients.', [
                    'deleted_user' => $deletedUser,
                    'deleted_by' => $deletedBy,
                ]);

                return false;
            }

            foreach ($adminEmails as $email) {
                $this->sendWithMailtrapThrottle(function () use ($email, $deletedUser, $deletedBy) {
                    Mail::html(
                        $this->accountDeletedEmailHtml($deletedUser, $deletedBy),
                        function ($message) use ($email) {
                            $message
                                ->to($email)
                                ->subject('NTI account was deleted');
                        }
                    );
                });
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Account deletion mail failed.', [
                'deleted_user' => $deletedUser,
                'deleted_by' => $deletedBy,
                'admin_emails' => $adminEmails->values()->all(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function accountDeletedEmailHtml(array $deletedUser, ?array $deletedBy): string
    {
        $deletedName = e(trim(($deletedUser['first_name'] ?? '') . ' ' . ($deletedUser['last_name'] ?? '')) ?: '-');
        $deletedEmail = e($deletedUser['email'] ?? '-');
        $deletedType = e(str_replace('_', ' ', $deletedUser['account_type'] ?? '-'));
        $deletedStatus = e($deletedUser['status'] ?? '-');

        $adminName = $deletedBy
            ? e(trim(($deletedBy['first_name'] ?? '') . ' ' . ($deletedBy['last_name'] ?? '')) ?: '-')
            : 'Unknown admin';

        $adminEmail = $deletedBy ? e($deletedBy['email'] ?? '-') : '-';
        $adminType = $deletedBy ? e(str_replace('_', ' ', $deletedBy['account_type'] ?? '-')) : '-';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NTI account was deleted</title>
</head>
<body style="margin: 0; padding: 0; background: #f3f4f6; font-family: Arial, Helvetica, sans-serif; color: #111827;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f3f4f6; padding: 32px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);">
                    <tr>
                        <td style="background: #991b1b; padding: 28px 32px;">
                            <div style="font-size: 13px; letter-spacing: 1.5px; text-transform: uppercase; color: #fee2e2; font-weight: 700;">
                                NTI Admin Notification
                            </div>
                            <h1 style="margin: 10px 0 0; color: #ffffff; font-size: 26px; line-height: 1.25;">
                                Account was deleted
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 18px; font-size: 16px; line-height: 1.6;">
                                A user account was permanently deleted from the NTI admin panel.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 14px; margin: 22px 0;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="margin: 0 0 10px; font-size: 14px; color: #991b1b; font-weight: 700;">Deleted user</p>

                                        <p style="margin: 0 0 8px; font-size: 16px;">
                                            <strong>Name:</strong> {$deletedName}
                                        </p>

                                        <p style="margin: 0 0 8px; font-size: 16px;">
                                            <strong>Email:</strong> {$deletedEmail}
                                        </p>

                                        <p style="margin: 0 0 8px; font-size: 16px;">
                                            <strong>Account type:</strong> {$deletedType}
                                        </p>

                                        <p style="margin: 0; font-size: 16px;">
                                            <strong>Previous status:</strong> {$deletedStatus}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 14px; margin: 22px 0;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="margin: 0 0 10px; font-size: 14px; color: #6b7280; font-weight: 700;">Deleted by</p>

                                        <p style="margin: 0 0 8px; font-size: 16px;">
                                            <strong>Name:</strong> {$adminName}
                                        </p>

                                        <p style="margin: 0 0 8px; font-size: 16px;">
                                            <strong>Email:</strong> {$adminEmail}
                                        </p>

                                        <p style="margin: 0; font-size: 16px;">
                                            <strong>Account type:</strong> {$adminType}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 14px; margin: 22px 0;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #9a3412;">
                                            This action cannot be undone from the admin panel.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 18px 32px; background: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 13px; color: #6b7280;">
                                This is an automatic notification from NTI.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function userPayload(User $user): array
    {
        return $user->only([
            'id',
            'first_name',
            'last_name',
            'email',
            'account_type',
            'status',
            'gdpr_consent',
            'created_at',
        ]);
    }

    private function sendWithMailtrapThrottle(callable $sendMail): void
    {
        if (!$this->isMailtrapMailer()) {
            $sendMail();

            return;
        }

        $delaySeconds = (int) env('MAILTRAP_SEND_DELAY_SECONDS', 11);

        if ($delaySeconds <= 0) {
            $sendMail();

            return;
        }

        $frameworkPath = storage_path('framework');

        if (!is_dir($frameworkPath)) {
            mkdir($frameworkPath, 0775, true);
        }

        $lockPath = $frameworkPath . DIRECTORY_SEPARATOR . 'mailtrap-mail-throttle.lock';
        $statePath = $frameworkPath . DIRECTORY_SEPARATOR . 'mailtrap-mail-next-time.txt';

        $lockFile = fopen($lockPath, 'c+');

        if (!$lockFile) {
            sleep($delaySeconds);
            $sendMail();

            return;
        }

        try {
            flock($lockFile, LOCK_EX);

            $now = microtime(true);
            $nextAvailableTime = $this->readNextAvailableMailTime($statePath);

            if ($nextAvailableTime > $now) {
                $waitSeconds = $nextAvailableTime - $now;
                usleep((int) round($waitSeconds * 1000000));
            }

            $sendMail();

            file_put_contents($statePath, (string) (microtime(true) + $delaySeconds));
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    private function isMailtrapMailer(): bool
    {
        $smtpHost = strtolower((string) config('mail.mailers.smtp.host', ''));

        return str_contains($smtpHost, 'mailtrap');
    }

    private function readNextAvailableMailTime(string $statePath): float
    {
        if (!file_exists($statePath)) {
            return 0.0;
        }

        $value = trim((string) file_get_contents($statePath));

        if ($value === '' || !is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }
}