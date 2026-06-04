<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyChallenge;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgramBDemoSeeder extends Seeder
{
    /**
     * Seeds a full Program B "company chooses team" scenario for the
     * existing Owen Company, so the flow can be verified end-to-end.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@btapp.com')->first();
        $owner = User::where('email', 'owenOwner@gmail.com')->first();

        if (!$admin || !$owner || !$owner->company_id) {
            $this->command->error('Expected admin@btapp.com and owenOwner@gmail.com (with a company) to exist.');
            return;
        }

        $company = Company::find($owner->company_id);

        $program = Program::firstOrCreate(
            ['type' => 'program_b', 'name' => 'Program B Pilot'],
            ['description' => 'Seeded Program B', 'is_active' => true]
        );

        $call = Call::firstOrCreate(
            ['title' => 'Program B Call 2026'],
            [
                'program_id' => $program->id,
                'created_by' => $admin->id,
                'status'     => 'open',
                'opens_at'   => now()->subDay(),
                'closes_at'  => now()->addMonth(),
            ]
        );

        $published = CompanyChallenge::firstOrCreate(
            ['title' => 'Live Challenge — Mobile App'],
            [
                'company_id'     => $company->id,
                'call_id'        => $call->id,
                'technical_spec' => 'Build a cross-platform mobile app for member onboarding.',
                'budget'         => 5000,
                'status'         => 'published',
            ]
        );

        CompanyChallenge::firstOrCreate(
            ['title' => 'Draft Challenge — Data Pipeline'],
            [
                'company_id'     => $company->id,
                'call_id'        => $call->id,
                'technical_spec' => 'Internal ETL pipeline for analytics.',
                'budget'         => 3000,
                'status'         => 'draft',
            ]
        );

        $teams = [
            ['alice', 'Alice', 'Team Alpha'],
            ['bob', 'Bob', 'Team Beta'],
        ];

        foreach ($teams as [$handle, $firstName, $teamName]) {
            $student = User::firstOrCreate(
                ['email' => $handle . '@student.com'],
                [
                    'first_name'        => $firstName,
                    'last_name'         => 'Student',
                    'password'          => bcrypt('student123'),
                    'account_type'      => 'student',
                    'status'            => 'active',
                    'email_verified_at' => now(),
                    'gdpr_consent'      => true,
                    'gdpr_consented_at' => now(),
                ]
            );

            $team = Team::firstOrCreate(
                ['name' => $teamName],
                ['leader_id' => $student->id]
            );

            DB::table('team_members')->updateOrInsert(
                ['team_id' => $team->id, 'user_id' => $student->id],
                ['role' => 'leader', 'joined_at' => now()]
            );

            Application::firstOrCreate(
                ['team_id' => $team->id, 'challenge_id' => $published->id],
                [
                    'call_id'      => $call->id,
                    'status'       => 'submitted',
                    'submitted_at' => now(),
                ]
            );
        }

        $this->command->info('Program B demo seeded: 2 challenges (1 published + 1 draft), 2 candidate teams on the published one.');
    }
}
