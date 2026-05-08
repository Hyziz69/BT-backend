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
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->count(10)->create([
            'account_type' => 'student',
            'status' => 'active',
        ]);
        // 1. Create a SuperAdmin for you to log in with
        $admin = User::factory()->create([
            'first_name' => 'NTI',
            'last_name' => 'Administrator',
            'email' => 'admin@nti.sk',
            'password' => Hash::make('password'),
            'account_type' => 'nti_admin',
            'status' => 'active',
        ]);

        // 2. Create the two main Programs
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

        // 3. Open a Call for Program B
        $call = Call::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'program_id' => $progB->id,
            'created_by' => $admin->id,
            'title' => 'Jar 2026 - Živá prax',
            'status' => 'open',
            'opens_at' => now(),
            'closes_at' => now()->addMonths(2),
        ]);

        // 4. Create a Partner Company and a CompanyChallenge
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

        // 5. Create a Student Team and an Application
        $leader = User::factory()->create([
            'account_type' => 'student',
            'status' => 'active'
        ]);

        $team = Team::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'leader_id' => $leader->id,
            'name' => 'The Nitra Innovators'
        ]);

        // Use sync() instead of attach() to prevent accidental double-insertion
        // and explicitly pass the ID string
        $team->members()->sync([
            (string) $leader->id => ['role' => 'leader']
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
