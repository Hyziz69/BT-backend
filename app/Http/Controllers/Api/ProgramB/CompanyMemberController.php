<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CompanyMemberController extends Controller
{
    /**
     * Roles the owner is allowed to assign. 'owner' is intentionally excluded —
     * ownership is unique and only changes via an explicit transfer.
     */
    private const ASSIGNABLE_ROLES = ['manager', 'member'];

    private const ROLE_LABELS = [
        'owner'   => 'Vlastník',
        'manager' => 'Manažér',
        'member'  => 'Člen',
    ];

    /**
     * GET /program-b/companies/{company}/members
     * List company members and pending invitations.
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        if ($request->user()->cannot('view', $company)) {
            return response()->json(['message' => 'Prístup zamietnutý.'], 403);
        }

        $members = $company->members()
            ->get(['id', 'first_name', 'last_name', 'email', 'company_role', 'status'])
            ->map(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->full_name,
                'email'      => $u->email,
                'role'       => $u->company_role,
                'role_label' => self::ROLE_LABELS[$u->company_role] ?? $u->company_role,
                'status'     => $u->status,
            ]);

        $invitations = $company->invitations()->pending()
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'role', 'status', 'expires_at']);

        return response()->json([
            'members'     => $members,
            'invitations' => $invitations,
        ]);
    }

    /**
     * POST /program-b/companies/{company}/members/invite
     * Owner invites a colleague by e-mail.
     */
    public function invite(Request $request, Company $company): JsonResponse
    {
        if ($request->user()->cannot('manageMembers', $company)) {
            return response()->json(['message' => 'Iba vlastník spoločnosti môže posielať pozvánky.'], 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role'  => ['nullable', 'in:' . implode(',', self::ASSIGNABLE_ROLES)],
        ]);

        $email = strtolower($validated['email']);
        $role  = $validated['role'] ?? 'member';

        // Reject if the e-mail already belongs to a member of this company.
        $existingMember = User::where('email', $email)
            ->where('company_id', $company->id)
            ->whereNotNull('company_role')
            ->exists();

        if ($existingMember) {
            return response()->json(['message' => 'Tento používateľ už je členom spoločnosti.'], 422);
        }

        // Reject if the e-mail belongs to a user already tied to a different company.
        $tiedElsewhere = User::where('email', $email)
            ->whereNotNull('company_id')
            ->where('company_id', '!=', $company->id)
            ->exists();

        if ($tiedElsewhere) {
            return response()->json(['message' => 'Tento používateľ už patrí k inej spoločnosti.'], 422);
        }

        $token = Str::random(64);

        $invitation = CompanyInvitation::updateOrCreate(
            ['company_id' => $company->id, 'email' => $email],
            [
                'token'      => $token,
                'role'       => $role,
                'status'     => 'pending',
                'expires_at' => now()->addDays(7),
            ]
        );

        $this->sendInvitationMail($request->user(), $company, $invitation);

        return response()->json([
            'message'    => 'Pozvánka bola odoslaná.',
            'invitation' => $invitation->only(['id', 'email', 'role', 'status', 'expires_at']),
        ], 201);
    }

    /**
     * GET /program-b/companies/invitations/{token}  (public)
     * Preview an invitation before accepting.
     */
    public function preview(string $token): JsonResponse
    {
        $invitation = CompanyInvitation::with('company')
            ->where('token', $token)
            ->pending()
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Pozvánka je neplatná alebo vypršala.'], 404);
        }

        return response()->json([
            'email'        => $invitation->email,
            'company_name' => $invitation->company->name,
            'role'         => $invitation->role,
            'role_label'   => self::ROLE_LABELS[$invitation->role] ?? $invitation->role,
            'expires_at'   => $invitation->expires_at,
        ]);
    }

    /**
     * POST /program-b/companies/invitations/accept  (auth)
     * Invitee accepts and is linked to the company.
     */
    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string']]);

        $invitation = CompanyInvitation::where('token', $validated['token'])
            ->pending()
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Pozvánka je neplatná alebo vypršala.'], 404);
        }

        $user = $request->user();

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return response()->json(['message' => 'Táto pozvánka je pre iný e-mail.'], 403);
        }

        if ($user->account_type !== 'company_contact') {
            return response()->json(['message' => 'Iba zástupcovia spoločnosti sa môžu pripojiť k spoločnosti.'], 403);
        }

        if ($user->belongsToCompany() && $user->company_id !== $invitation->company_id) {
            return response()->json(['message' => 'Už patríte k inej spoločnosti.'], 422);
        }

        // Account still pending admin approval — remember the user so we can finish later.
        if ($user->status !== 'active') {
            $invitation->update(['registered_user_id' => $user->id]);

            return response()->json([
                'message' => 'Účet čaká na schválenie. Po schválení budete automaticky pridaný do spoločnosti.',
                'status'  => $user->status,
            ], 403);
        }

        $user->update([
            'company_id'   => $invitation->company_id,
            'company_role' => $invitation->role,
        ]);

        $invitation->update(['status' => 'accepted']);

        return response()->json([
            'message'    => 'Úspešne ste sa pripojili k spoločnosti.',
            'company_id' => $invitation->company_id,
        ]);
    }

    /**
     * PATCH /program-b/companies/{company}/members/{user}
     * Owner changes a member's role.
     */
    public function updateRole(Request $request, Company $company, User $user): JsonResponse
    {
        if ($request->user()->cannot('manageMembers', $company)) {
            return response()->json(['message' => 'Iba vlastník spoločnosti môže meniť role.'], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:' . implode(',', self::ASSIGNABLE_ROLES)],
        ]);

        $guard = $this->guardTargetMember($company, $user);
        if ($guard) {
            return $guard;
        }

        $user->update(['company_role' => $validated['role']]);

        return response()->json([
            'message' => 'Rola bola aktualizovaná.',
            'member'  => [
                'id'   => $user->id,
                'role' => $user->company_role,
            ],
        ]);
    }

    /**
     * DELETE /program-b/companies/{company}/members/{user}
     * Owner removes a member from the company.
     */
    public function kick(Request $request, Company $company, User $user): JsonResponse
    {
        if ($request->user()->cannot('manageMembers', $company)) {
            return response()->json(['message' => 'Iba vlastník spoločnosti môže odstrániť členov.'], 403);
        }

        $guard = $this->guardTargetMember($company, $user);
        if ($guard) {
            return $guard;
        }

        $user->update(['company_id' => null, 'company_role' => null]);

        return response()->json(['message' => 'Člen bol odstránený zo spoločnosti.']);
    }

    /**
     * POST /program-b/companies/{company}/members/leave
     * A non-owner member leaves the company voluntarily.
     */
    public function leave(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if ($user->company_id !== $company->id || is_null($user->company_role)) {
            return response()->json(['message' => 'Nie ste členom tejto spoločnosti.'], 422);
        }

        if ($user->isCompanyOwner()) {
            return response()->json([
                'message' => 'Vlastník nemôže opustiť spoločnosť. Najprv preneste vlastníctvo.',
            ], 422);
        }

        $user->update(['company_id' => null, 'company_role' => null]);

        return response()->json(['message' => 'Opustili ste spoločnosť.']);
    }

    /**
     * Shared guard for role-change / kick targets. Returns an error response or null.
     * Ensures the target is a real company_contact member of THIS company and not the owner —
     * notably this prevents touching student accounts.
     */
    private function guardTargetMember(Company $company, User $user): ?JsonResponse
    {
        if ($user->company_id !== $company->id || is_null($user->company_role)) {
            return response()->json(['message' => 'Tento používateľ nie je členom spoločnosti.'], 422);
        }

        if ($user->account_type !== 'company_contact') {
            return response()->json(['message' => 'Túto rolu nie je možné meniť.'], 422);
        }

        if ($user->isCompanyOwner()) {
            return response()->json(['message' => 'Vlastníka spoločnosti nie je možné upraviť.'], 422);
        }

        return null;
    }

    private function sendInvitationMail(User $inviter, Company $company, CompanyInvitation $invitation): void
    {
        try {
            $frontend  = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
            $acceptUrl = $frontend . '/company-invitation/accept?token=' . $invitation->token;
            $hasAccount = User::where('email', $invitation->email)->exists();

            Mail::send('emails.company-invitation', [
                'inviterName' => $inviter->full_name,
                'companyName' => $company->name,
                'roleLabel'   => self::ROLE_LABELS[$invitation->role] ?? $invitation->role,
                'acceptUrl'   => $acceptUrl,
                'hasAccount'  => $hasAccount,
            ], function ($message) use ($invitation, $company) {
                $message->to($invitation->email)
                        ->subject('Pozvánka do spoločnosti ' . $company->name . ' | NTI');
            });
        } catch (\Throwable $e) {
            Log::error('Company invitation mail failed: ' . $e->getMessage(), [
                'company_id' => $company->id,
                'email'      => $invitation->email,
            ]);
        }
    }
}
