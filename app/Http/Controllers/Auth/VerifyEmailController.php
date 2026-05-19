<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminApprovalRequestMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerifyEmailController extends Controller
{
    // GET /api/email/verify/{id}/{hash}  (signed URL)
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if (!$request->hasValidSignature()) {
            return response()->json(['message' => 'Verification link has expired or is invalid.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.']);
        }

        $user->markEmailAsVerified();

        // Notify admins now that the user has verified their email
        try {
            $adminEmails = User::where('account_type', 'nti_admin')
                ->pluck('email')
                ->filter()
                ->unique();

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new AdminApprovalRequestMail($user));
            }
        } catch (\Throwable $e) {
            Log::error('AdminApprovalRequestMail failed after verification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Email verified successfully. Please wait for admin approval.']);
    }

    // POST /api/email/verify/resend
    public function resend(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        // Always return 200 to avoid user enumeration
        if (!$user || $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'If this email exists and is unverified, a new link has been sent.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }
}
