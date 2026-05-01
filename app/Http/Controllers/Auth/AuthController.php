<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name'        => $request->first_name,
            'last_name'         => $request->last_name,
            'email'             => $request->email,
            'password'          => $request->password,
            'account_type'      => $request->account_type,
            'status'            => 'pending',
            'gdpr_consent'      => true,
            'gdpr_consented_at' => now(),
        ]);

        event(new Registered($user));

        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.',
        ], 201);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->email))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        $user->markEmailAsVerified();
        $user->status = 'active';
        $user->save();

        return response()->json(['message' => 'Email verified successfully.'], 200);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $token = Auth::attempt([
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        if (! $token) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            return response()->json(['message' => 'Please verify your email before logging in.'], 403);
        }

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user'         => [
                'id'           => $user->id,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'email'        => $user->email,
                'account_type' => $user->account_type,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'id'           => $user->id,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->email,
            'account_type' => $user->account_type,
            'status'       => $user->status,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If that email exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            return response()->json(['message' => __($status)], 400);
        }

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
