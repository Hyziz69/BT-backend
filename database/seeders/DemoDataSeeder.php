<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyChallenge;
use App\Models\Consultation;
use App\Models\Evaluation;
use App\Models\Mentorship;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Fills the app with clickable demo data across Program A & B:
     * companies, mentors, students, teams, challenges in every status,
     * applications, mentorships, consultations, milestones, evaluations,
     * and notifications. Idempotent — safe to re-run.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@btapp.com'],
            [
                'first_name' => 'Admin', 'last_name' => 'User',
                'password' => bcrypt('admin123'), 'account_type' => 'superadmin',
                'status' => 'active', 'gdpr_consent' => true, 'gdpr_consented_at' => now(),
            ]
        );
        $admin->forceFill(['email_verified_at' => now()])->save();

        // ---- Helpers -------------------------------------------------------
        $mkUser = function (string $email, string $first, string $last, string $type, array $extra = []): User {
            $u = User::firstOrCreate(
                ['email' => $email],
                array_merge([
                    'first_name' => $first, 'last_name' => $last,
                    'password' => bcrypt('password123'), 'account_type' => $type,
                    'status' => 'active', 'gdpr_consent' => true, 'gdpr_consented_at' => now(),
                ], $extra)
            );
            $u->forceFill(['email_verified_at' => now()])->save();
            return $u;
        };

        $mkStudent = function (string $email, string $first, string $last, array $skills = []) use ($mkUser): User {
            $u = $mkUser($email, $first, $last, 'student');
            StudentProfile::updateOrCreate(
                ['user_id' => $u->id],
                [
                    'cv_url' => 'https://example.com/cv/' . $u->id . '.pdf',
                    'study_program' => 'Computer Science', 'study_year' => rand(1, 5),
                    'skills' => $skills, 'academic_declaration' => true,
                ]
            );
            return $u;
        };

        $mkTeam = function (string $name, User $leader, array $members = [], array $comp = []) use (&$teamCount): Team {
            $team = Team::firstOrCreate(['name' => $name], ['leader_id' => $leader->id, 'competencies' => $comp]);
            foreach (array_merge([$leader], $members) as $i => $m) {
                DB::table('team_members')->updateOrInsert(
                    ['team_id' => $team->id, 'user_id' => $m->id],
                    ['role' => $i === 0 ? 'leader' : 'member', 'joined_at' => now()]
                );
            }
            return $team;
        };

        $mkCompany = function (string $name, string $ico, string $sector, User $owner) use (&$x): Company {
            $company = Company::firstOrCreate(['name' => $name], ['ico' => $ico, 'sector' => $sector, 'status' => 'active']);
            $owner->update(['company_id' => $company->id, 'company_role' => 'owner']);
            return $company;
        };

        $mkChallenge = function (Company $company, Call $call, string $title, string $status, ?float $budget, string $spec, ?User $po = null, ?Team $team = null): CompanyChallenge {
            return CompanyChallenge::firstOrCreate(
                ['title' => $title],
                [
                    'company_id' => $company->id, 'call_id' => $call->id,
                    'technical_spec' => $spec, 'budget' => $budget, 'status' => $status,
                    'product_owner_id' => $po?->id, 'team_id' => $team?->id,
                ]
            );
        };

        $mkAppB = function (Team $team, Call $call, CompanyChallenge $challenge, string $status): Application {
            return Application::firstOrCreate(
                ['team_id' => $team->id, 'challenge_id' => $challenge->id],
                [
                    'call_id' => $call->id, 'status' => $status,
                    'motivation_letter' => 'Our team is excited to take on this challenge.',
                    'solution_proposal' => 'We propose an iterative, well-tested delivery.',
                    'submitted_at' => now()->subDays(rand(2, 14)),
                    'decided_at' => in_array($status, ['approved', 'rejected', 'archived']) ? now()->subDays(rand(0, 3)) : null,
                ]
            );
        };

        $mkAppA = function (Team $team, Call $call, string $status) use (&$y): Application {
            $existing = Application::where('team_id', $team->id)->whereNull('challenge_id')->where('call_id', $call->id)->first();
            if ($existing) return $existing;
            return Application::create([
                'team_id' => $team->id, 'call_id' => $call->id, 'challenge_id' => null,
                'status' => $status,
                'motivation_letter' => 'We have a strong project idea for this call.',
                'solution_proposal' => 'A novel approach with clear milestones.',
                'score' => in_array($status, ['in_evaluation', 'approved', 'active']) ? rand(60, 95) : null,
                'submitted_at' => now()->subDays(rand(5, 20)),
                'decided_at' => in_array($status, ['approved', 'rejected']) ? now()->subDays(rand(0, 4)) : null,
            ]);
        };

        // ---- Programs & Calls ---------------------------------------------
        $progB = Program::firstOrCreate(['type' => 'program_b', 'name' => 'Program B Pilot'], ['description' => 'Company challenges', 'is_active' => true]);
        $progA = Program::firstOrCreate(['type' => 'program_a', 'name' => 'First Program'], ['description' => 'Student-led projects', 'is_active' => true]);

        $callB = Call::firstOrCreate(['title' => 'Program B Call 2026'], [
            'program_id' => $progB->id, 'created_by' => $admin->id, 'status' => 'open',
            'opens_at' => now()->subDays(10), 'closes_at' => now()->addMonth(),
        ]);
        $callA = Call::firstOrCreate(['title' => 'Program A Call 2026'], [
            'program_id' => $progA->id, 'created_by' => $admin->id, 'status' => 'open',
            'opens_at' => now()->subDays(10), 'closes_at' => now()->addMonth(),
        ]);

        // ---- Mentors ------------------------------------------------------
        $mentor1 = $mkUser('martin.mentor@demo.com', 'Martin', 'Mentor', 'mentor');
        $mentor2 = $mkUser('olena.mentor@demo.com', 'Olena', 'Mentor', 'mentor');

        // ---- Companies ----------------------------------------------------
        $owenOwner = User::where('email', 'owenOwner@gmail.com')->first();
        $owen = $owenOwner ? Company::find($owenOwner->company_id) : null;
        if (!$owen) {
            $owenOwner = $mkUser('owen.owner@demo.com', 'Owen', 'Owner', 'company_contact');
            $owen = $mkCompany('Owen Company', '12345678', 'IT', $owenOwner);
        }

        $nordicOwner = $mkUser('nordic.owner@demo.com', 'Nina', 'Nordic', 'company_contact');
        $nordic = $mkCompany('Nordic Soft', '87654321', 'Fintech', $nordicOwner);

        $byteOwner = $mkUser('byte.owner@demo.com', 'Boris', 'Byte', 'company_contact');
        $byte = $mkCompany('ByteForge', '11223344', 'Gaming', $byteOwner);

        // ---- Students & Teams ---------------------------------------------
        $s = [];
        foreach ([
            ['alice@student.com', 'Alice', 'Anderson'], ['bob@student.com', 'Bob', 'Brown'],
            ['carol@student.com', 'Carol', 'Clark'], ['dave@student.com', 'Dave', 'Davis'],
            ['eve@student.com', 'Eve', 'Evans'], ['frank@student.com', 'Frank', 'Foster'],
            ['grace@student.com', 'Grace', 'Green'], ['henry@student.com', 'Henry', 'Hill'],
            ['iris@student.com', 'Iris', 'Irwin'], ['jack@student.com', 'Jack', 'Jones'],
            ['kate@student.com', 'Kate', 'King'], ['leo@student.com', 'Leo', 'Lewis'],
        ] as $row) {
            $s[explode('@', $row[0])[0]] = $mkStudent($row[0], $row[1], $row[2], ['PHP', 'Vue', 'SQL']);
        }

        $teamAlpha = $mkTeam('Team Alpha', $s['alice'], [$s['dave']], ['Backend', 'DevOps']);
        $teamBeta  = $mkTeam('Team Beta', $s['bob'], [$s['eve']], ['Frontend', 'UX']);
        $teamGamma = $mkTeam('Team Gamma', $s['carol'], [$s['frank']], ['Mobile', 'API']);
        $teamDelta = $mkTeam('Team Delta', $s['grace'], [$s['henry']], ['Data', 'ML']);
        $teamEps   = $mkTeam('Team Epsilon', $s['iris'], [$s['jack']], ['Cloud', 'Security']);
        $teamZeta  = $mkTeam('Team Zeta', $s['kate'], [$s['leo']], ['Game', 'Graphics']);

        // ---- Program B challenges in every status -------------------------
        // Owen
        $owenPub  = $mkChallenge($owen, $callB, 'API Integration Platform', 'published', 7500, 'Build a middleware that syncs CRM and billing systems via REST APIs.');
        $owenAsg  = $mkChallenge($owen, $callB, 'Live Challenge — Mobile App', 'assigned', 5000, 'Cross-platform mobile app for member onboarding.', $owenOwner, $teamAlpha);
        $mkChallenge($owen, $callB, 'Draft Challenge — Data Pipeline', 'draft', 3000, 'Internal ETL pipeline for analytics.');
        $owenProg = $mkChallenge($owen, $callB, 'IoT Sensor Dashboard', 'in_progress', 9000, 'Realtime dashboard for factory IoT sensors.', $owenOwner, $teamDelta);
        $owenClsd = $mkChallenge($owen, $callB, 'Legacy System Migration', 'closed', 12000, 'Migrate a legacy PHP monolith to a modular API.', $owenOwner, $teamEps);

        // Nordic
        $nordicPub = $mkChallenge($nordic, $callB, 'Analytics Engine', 'published', 8000, 'Streaming analytics engine for transaction data.');
        $mkChallenge($nordic, $callB, 'Mobile Wallet Redesign', 'matching', 6500, 'Redesign the mobile wallet UX with accessibility focus.');

        // ByteForge
        $byteAsg = $mkChallenge($byte, $callB, 'Game Backend Services', 'assigned', 11000, 'Scalable matchmaking and leaderboard backend.', $byteOwner, $teamZeta);

        // ---- Program B applications ---------------------------------------
        $mkAppB($teamGamma, $callB, $owenPub, 'submitted');                 // candidate
        $mkAppB($teamBeta,  $callB, $nordicPub, 'submitted');               // candidate
        $mkAppB($teamZeta,  $callB, $nordicPub, 'submitted');               // candidate
        $appAlphaB = $mkAppB($teamAlpha, $callB, $owenAsg, 'approved');     // selected
        $appBetaRej = $mkAppB($teamBeta, $callB, $owenAsg, 'rejected');     // rejected on same
        $appDeltaB = $mkAppB($teamDelta, $callB, $owenProg, 'active');      // running
        $appEpsB   = $mkAppB($teamEps,  $callB, $owenClsd, 'archived');     // finished
        $appZetaB  = $mkAppB($teamZeta, $callB, $byteAsg, 'approved');      // selected

        // ---- Program A applications (various statuses) --------------------
        $appAlphaA = $mkAppA($teamAlpha, $callA, 'in_evaluation');
        $appGammaA = $mkAppA($teamGamma, $callA, 'submitted');
        $appDeltaA = $mkAppA($teamDelta, $callA, 'approved');
        $appEpsA   = $mkAppA($teamEps,  $callA, 'active');

        // ---- Evaluations (admin acts as evaluator) ------------------------
        foreach ([$appAlphaA, $appDeltaA, $appEpsA] as $app) {
            foreach (['Innovation' => rand(60, 95), 'Feasibility' => rand(60, 95), 'Team' => rand(60, 95)] as $crit => $score) {
                Evaluation::firstOrCreate(
                    ['application_id' => $app->id, 'evaluator_id' => $admin->id, 'criterion' => $crit],
                    ['score' => $score, 'comment' => 'Solid work on ' . strtolower($crit) . '.']
                );
            }
        }

        // ---- Mentorships + milestones + consultations ---------------------
        $withMentoring = [
            [$appDeltaB, $mentor1], [$appEpsB, $mentor2],
            [$appZetaB, $mentor1], [$appEpsA, $mentor2], [$appDeltaA, $mentor1],
        ];
        foreach ($withMentoring as [$app, $mentor]) {
            $ms = Mentorship::firstOrCreate(
                ['application_id' => $app->id, 'mentor_id' => $mentor->id],
                ['started_at' => now()->subDays(rand(5, 20)), 'notes' => 'Kick-off mentoring session completed.']
            );

            foreach ([
                ['Project kick-off', 'completed', now()->subDays(14)],
                ['MVP delivery', 'in_progress', now()->addDays(7)],
                ['Final presentation', 'pending', now()->addDays(21)],
            ] as $i => [$title, $st, $due]) {
                Milestone::firstOrCreate(
                    ['application_id' => $app->id, 'title' => $title],
                    ['status' => $st, 'due_date' => $due, 'comment' => 'Auto-seeded milestone.']
                );
            }

            Consultation::firstOrCreate(
                ['mentorship_id' => $ms->id, 'scheduled_at' => now()->subDays(7)],
                ['notes' => 'Reviewed architecture and sprint plan.', 'feedback' => 'Good progress, keep tests green.']
            );
        }

        // ---- Notifications ------------------------------------------------
        foreach ([$s['alice'], $s['grace'], $s['iris'], $owenOwner, $nordicOwner] as $u) {
            Notification::firstOrCreate(
                ['user_id' => $u->id, 'type' => 'welcome'],
                ['subject' => 'Welcome to NTI Portal', 'body' => 'Your account is ready. Explore your dashboard.', 'is_read' => false, 'sent_at' => now()]
            );
        }
        Notification::firstOrCreate(
            ['user_id' => $s['carol']->id, 'type' => 'application_submitted'],
            ['subject' => 'Application submitted', 'body' => 'Your team applied to "API Integration Platform".', 'is_read' => false, 'sent_at' => now()]
        );

        $this->command->info('Demo data seeded: 3 companies, 2 mentors, 12 students, 6 teams, 8 Program B challenges (all statuses), Program A + B applications, mentorships, milestones, consultations, evaluations & notifications.');
    }
}
