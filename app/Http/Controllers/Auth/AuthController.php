<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminApprovalRequestMail;
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

        $mailSent = true;

        try {
            $adminEmails = User::query()
                ->where('account_type', 'nti_admin')
                ->pluck('email')
                ->filter()
                ->unique()
                ->values();

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new AdminApprovalRequestMail($user));
            }
        } catch (\Throwable $e) {
            $mailSent = false;
            Log::error('AdminApprovalRequestMail failed', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message'   => $mailSent
                ? 'Registration submitted successfully. Please wait for admin approval and watch your email.'
                : 'Registration submitted successfully, but notification email was not sent.',
            'status'    => 'pending',
            'mail_sent' => $mailSent,
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
            return response()->json(['message' => 'Your account was not approved.'], 403);
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
        $request->validate(['email' => ['required', 'email']]);

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
}