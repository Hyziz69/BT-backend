<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Program;
use App\Models\Call;
use App\Models\CompanyChallenge;
use App\Models\Team;
use App\Models\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'first_name' => 'NTI',
            'last_name' => 'Administrator',
            'email' => 'admin@nti.sk',
            'password' => Hash::make('password'),
            'account_type' => 'nti_admin',
            'status' => 'active',
        ]);

        $progA = Program::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'program_a',
            'name' => 'Grantový inkubačný program',
            'description' => 'Focus na vlastné inovatívne nápady.',
        ]);

        $progB = Program::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'program_b',
            'name' => 'Program živej praxe',
            'description' => 'Focus na reálne zadania od firiem.',
        ]);

        $call = Call::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'program_id' => $progB->id,
            'created_by' => $admin->id,
            'title' => 'Jar 2026 - Živá prax',
            'status' => 'open',
            'opens_at' => now(),
            'closes_at' => now()->addMonths(2),
        ]);

        // 4. Create a Partner Company and a Challenge
        $company = Company::factory()->create(['name' => 'NitraTech Solutions']);

        $challenge = CompanyChallenge::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'call_id' => $call->id,
            'company_id' => $company->id,
            'title' => 'Smart City Data Visualizer',
            'technical_spec' => 'Vývoj dashboardu pre vizualizáciu IoT dát z Nitry.',
            'budget' => 2500.00,
            'status' => 'published',
        ]);

        $leader = User::factory()->create([
            'password' => Hash::make('password'),
            'account_type' => 'student',
            'status' => 'active',
        ]);

        \App\Models\StudentProfile::create([
            'user_id' => $leader->id,
            'cv_path' => 'dummy/cv_leader_' . $leader->id . '.pdf',
            'study_program' => 'Aplikovaná informatika',
            'study_year' => 3,
        ]);

        $students = User::factory(6)->create([
            'password' => Hash::make('password'),
            'account_type' => 'student',
            'status' => 'active',
        ]);

        foreach ($students as $student) {
            \App\Models\StudentProfile::create([
                'user_id' => $student->id,
                'cv_path' => 'dummy/cv_student_' . $student->id . '.pdf',
                'study_program' => 'Aplikovaná informatika',
                'study_year' => 2,
            ]);
        }

        $mentors = User::factory(3)->create([
            'password' => Hash::make('password'),
            'account_type' => 'mentor',
            'status' => 'active',
        ]);


        $team = Team::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'leader_id' => $leader->id,
            'name' => 'The Nitra Innovators',
            'competencies' => '[]'
        ]);

        \App\Models\TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $leader->id,
            'role'    => 'leader',
        ]);

        Application::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'call_id' => $call->id,
            'team_id' => $team->id,
            'challenge_id' => $challenge->id,
            'status' => 'submitted',
            'motivation_letter' => 'Chceme pomôcť mestu Nitra s lepším prehľadom o doprave.',
            'submitted_at' => now(),
        ]);
    }
}
