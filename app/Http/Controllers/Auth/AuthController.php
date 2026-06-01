<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminApprovalRequestMail;
use App\Mail\RegistrationSubmittedMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'account_type' => ['required', 'in:student,mentor,company_contact'],
            'gdpr_consent' => ['accepted'],
        ]);

        $user = User::create([
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'],
            'email'             => $validated['email'],
            'password'          => $validated['password'],
            'account_type'      => $validated['account_type'],
            'status'            => 'pending',
            'gdpr_consent'      => true,
            'gdpr_consented_at' => now(),
        ]);

        $adminMailSent = $this->sendAdminApprovalNotification($user);
        $userMailSent = $this->sendRegistrationSubmittedNotification($user);

        return response()->json([
            'message' => 'Registration submitted successfully. Please wait for admin approval.',
            'status' => 'pending',
            'mail_sent' => [
                'admin' => $adminMailSent,
                'user' => $userMailSent,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        /** @var User $user */
        $user = auth('api')->user();

        if ($user->status === 'pending') {
            auth('api')->logout();

            return response()->json(['message' => 'Your account is waiting for admin approval.'], 403);
        }

        if ($user->status === 'suspended') {
            auth('api')->logout();

            return response()->json(['message' => 'Your account has been rejected.'], 403);
        }

        return $this->respondWithToken($token, $user);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(): JsonResponse
    {
        return response()->json(auth('api')->user());
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['message' => __($status)], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['message' => __($status)], 422);
    }

    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => $user,
        ]);
    }

    private function sendAdminApprovalNotification(User $user): bool
    {
        try {
            $adminEmails = User::query()
                ->whereIn('account_type', ['nti_admin', 'superadmin'])
                ->where('status', 'active')
                ->pluck('email')
                ->filter()
                ->unique()
                ->values();

            if ($adminEmails->isEmpty()) {
                Log::warning('AdminApprovalRequestMail was not sent: no active admin recipients.', [
                    'new_user_id' => $user->id,
                    'new_user_email' => $user->email,
                ]);

                return false;
            }

            foreach ($adminEmails as $email) {
                $this->sendWithMailtrapThrottle(function () use ($email, $user) {
                    Mail::to($email)->send(new AdminApprovalRequestMail($user));
                });
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('AdminApprovalRequestMail failed.', [
                'new_user_id' => $user->id,
                'new_user_email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendRegistrationSubmittedNotification(User $user): bool
    {
        try {
            $this->sendWithMailtrapThrottle(function () use ($user) {
                Mail::to($user->email)->send(new RegistrationSubmittedMail($user));
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('RegistrationSubmittedMail failed.', [
                'new_user_id' => $user->id,
                'new_user_email' => $user->email,
                'account_type' => $user->account_type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
