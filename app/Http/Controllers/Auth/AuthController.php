$user = User::create([
    'first_name' => $validated['first_name'],
    'last_name' => $validated['last_name'],
    'email' => $validated['email'],
    'password' => $validated['password'],
    'account_type' => $validated['account_type'],
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