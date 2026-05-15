<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\AdminApprovalRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Регистрация пользователя (твоя логика)
     */
    public function register(Request $request)
    {
        $validated = $request->all();

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'pending',
            'gdpr_consent' => true,
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
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $mailSent
                ? 'Registration submitted successfully. Please wait for admin approval and watch your email.'
                : 'Registration submitted successfully, but notification email was not sent.',
            'status' => 'pending',
            'mail_sent' => $mailSent,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = auth('api')->user();

        if (!$user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email not verified.'], 403);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'account_type' => $user->account_type,
            ]
        ], 200);
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Logged out successfully.'], 200);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
