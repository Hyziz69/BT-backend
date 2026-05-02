<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeamInvitationController extends Controller
{
    // POST /api/program-a/teams/{team}/invitations
    public function send(Request $request, Team $team)
    {
        if ($team->leader_id !== $request->user()->id) {
            return response()->json(['message' => 'Iba vedúci tímu môže posielať pozvánky.'], 403);
        }

        $validated = $request->validate([
            'emails'   => 'required|array|min:1|max:10',
            'emails.*' => 'required|email',
        ]);

        $results = [];

        foreach ($validated['emails'] as $email) {
            $alreadyMember = TeamMember::whereHas('user', fn($q) => $q->where('email', $email))
                ->where('team_id', $team->id)->exists();

            if ($alreadyMember) {
                $results[$email] = 'already_member';
                continue;
            }

            $existingInvite = TeamInvitation::where('team_id', $team->id)
                ->where('email', $email)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvite) {
                $results[$email] = 'already_invited';
                continue;
            }

            $token = Str::random(64);

            TeamInvitation::updateOrCreate(
                ['team_id' => $team->id, 'email' => $email],
                ['token' => $token, 'status' => 'pending', 'expires_at' => now()->addDays(7)]
            );

            $acceptUrl = config('app.frontend_url') . '/invitation/accept?token=' . $token;
            $hasAccount = User::where('email', $email)->exists();

            Mail::send('emails.team-invitation', [
                'teamName'   => $team->name,
                'leaderName' => $request->user()->first_name . ' ' . $request->user()->last_name,
                'acceptUrl'  => $acceptUrl,
                'hasAccount' => $hasAccount,
            ], function ($message) use ($email, $team) {
                $message->to($email)->subject('Pozvánka do tímu ' . $team->name . ' | NTI');
            });

            $results[$email] = 'invited';
        }

        return response()->json(['message' => 'Pozvánky odoslané.', 'results' => $results]);
    }

    // GET /api/program-a/invitations/{token} — public, no auth needed
    public function preview(string $token)
    {
        $invitation = TeamInvitation::with('team')
            ->where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Pozvánka je neplatná alebo vypršala.'], 404);
        }

        return response()->json([
            'email'      => $invitation->email,
            'team_name'  => $invitation->team->name,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    // POST /api/program-a/invitations/accept — auth required, pending users allowed
    public function accept(Request $request)
    {
        $validated = $request->validate(['token' => 'required|string']);

        $invitation = TeamInvitation::where('token', $validated['token'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Pozvánka je neplatná alebo vypršala.'], 404);
        }

        $user = $request->user();

        if ($user->email !== $invitation->email) {
            return response()->json(['message' => 'Táto pozvánka je pre iný e-mail.'], 403);
        }

        if ($user->status !== 'active') {
            // Store their user id so admin approval can auto-add them later
            $invitation->update(['registered_user_id' => $user->id]);
            return response()->json([
                'message' => 'Účet čaká na schválenie. Po schválení budete automaticky pridaný do tímu.',
                'status'  => $user->status,
            ], 403);
        }

        TeamMember::firstOrCreate([
            'team_id' => $invitation->team_id,
            'user_id' => $user->id,
        ]);

        $invitation->update(['status' => 'accepted']);

        return response()->json(['message' => 'Úspešne ste sa pripojili k tímu.', 'team_id' => $invitation->team_id]);
    }

    // GET /api/program-a/teams/{team}/invitations
    public function index(Request $request, Team $team)
    {
        if ($team->leader_id !== $request->user()->id) {
            return response()->json(['message' => 'Prístup zamietnutý.'], 403);
        }

        return response()->json(
            TeamInvitation::where('team_id', $team->id)
                ->orderByDesc('created_at')
                ->get()
        );
    }
}