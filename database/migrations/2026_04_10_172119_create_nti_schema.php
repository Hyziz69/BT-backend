<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('ico', 20)->nullable()->unique()->comment('Company registration number (IČO)');
            $table->string('sector', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_url')->nullable();
            $table->enum('status', ['active', 'pending', 'inactive'])->default('pending');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('account_type', [
                'student',
                'mentor',
                'company_contact',
                'editor',
                'nti_admin',
                'superadmin',
            ])->default('student');
            $table->enum('status', ['active', 'pending', 'suspended'])->default('pending');
            $table->boolean('gdpr_consent')->default(false);
            $table->timestamp('gdpr_consented_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('company_id');
            $table->index('account_type');
            $table->index('status');
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                  ->unique()
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('study_program', 200)->nullable();
            $table->unsignedTinyInteger('study_year')->nullable();
            $table->json('skills')->nullable()->comment('Array of skill strings');
            $table->string('cv_url')->nullable();
            $table->boolean('academic_declaration')->default(false)
                  ->comment('Čestné vyhlásenie – no repeated subjects, GPA ok');
            $table->text('academic_notes')->nullable()
                  ->comment('Internal notes for commission review');
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['program_a', 'program_b']);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('min_team_size')->default(3);
            $table->unsignedTinyInteger('max_team_size')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')
                  ->constrained('programs')
                  ->cascadeOnDelete();
            $table->foreignUuid('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'open', 'evaluating', 'closed'])->default('draft');
            $table->json('evaluation_criteria')->nullable()
                  ->comment('Array of {criterion, weight} objects');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->index('program_id');
            $table->index('status');
        });

        Schema::create('company_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_id')
                  ->constrained('calls')
                  ->cascadeOnDelete();
            $table->foreignUuid('company_id')
                  ->constrained('companies')
                  ->restrictOnDelete();
            $table->foreignUuid('product_owner_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Company-side Product Owner (user account)');
            $table->string('title', 300);
            $table->longText('technical_spec')->nullable();
            $table->decimal('budget', 10, 2)->nullable()->comment('Student reward budget in EUR');
            $table->enum('status', [
                'draft',
                'published',
                'matching',
                'assigned',
                'in_progress',
                'closed',
            ])->default('draft');
            $table->timestamps();

            $table->index('call_id');
            $table->index('company_id');
            $table->index('status');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('leader_id')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->string('name', 200);
            $table->json('competencies')->nullable()->comment('Array of competency strings');
            $table->timestamps();

            $table->index('leader_id');
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->foreignUuid('team_id')
                  ->constrained('teams')
                  ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->enum('role', ['leader', 'member'])->default('member');
            $table->timestamp('joined_at')->useCurrent();

            $table->primary(['team_id', 'user_id']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_id')
                  ->constrained('calls')
                  ->restrictOnDelete();
            $table->foreignUuid('team_id')
                  ->constrained('teams')
                  ->restrictOnDelete();
            $table->foreignUuid('challenge_id')
                  ->nullable()
                  ->constrained('company_challenges')
                  ->nullOnDelete()
                  ->comment('Set only for Program B applications');
            $table->enum('status', [
                'draft',
                'submitted',
                'formally_verified',
                'in_evaluation',
                'pending_supplement',
                'approved',
                'rejected',
                'onboarding',
                'active',
                'paused',
                'completed',
                'archived',
            ])->default('draft');
            $table->text('motivation_letter')->nullable();
            $table->text('solution_proposal')->nullable();
            $table->decimal('score', 5, 2)->nullable()->comment('Aggregate evaluation score');
            $table->text('decision_notes')->nullable()->comment('Internal commission notes');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('call_id');
            $table->index('team_id');
            $table->index('status');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                  ->constrained('applications')
                  ->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->enum('doc_type', [
                'executive_summary',
                'tech_architecture',
                'roadmap',
                'budget',
                'risk_analysis',
                'monetization',
                'cv',
                'attachment',
                'other',
            ])->default('attachment');
            $table->enum('classification', ['public', 'internal', 'confidential'])
                  ->default('internal');
            $table->string('filename');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('Size in bytes');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();

            $table->index('application_id');
            $table->index('doc_type');
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                  ->constrained('applications')
                  ->cascadeOnDelete();
            $table->foreignUuid('evaluator_id')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->string('criterion', 200);
            $table->decimal('score', 5, 2);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('evaluator_id');
        });

        Schema::create('mentorships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                  ->constrained('applications')
                  ->cascadeOnDelete();
            $table->foreignUuid('mentor_id')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('mentor_id');
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                  ->constrained('applications')
                  ->cascadeOnDelete();
            $table->string('title', 300);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])
                  ->default('pending');
            $table->date('due_date')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('status');
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mentorship_id')
                  ->constrained('mentorships')
                  ->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->text('notes')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index('mentorship_id');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('type', 100)->comment('e.g. application_status_changed, mentor_assigned');
            $table->string('subject', 300)->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('action', 100)->comment('e.g. application.status_changed, user.role_updated');
            $table->string('entity_type', 100)->comment('e.g. App\\Models\\Application');
            $table->uuid('entity_id')->nullable();
            $table->json('payload')->nullable()->comment('Before/after snapshot or extra context');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('mentorships');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('company_challenges');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
    }
};