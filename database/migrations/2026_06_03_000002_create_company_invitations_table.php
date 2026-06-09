<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                  ->constrained('companies')
                  ->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            // Role the invitee will receive once they accept.
            $table->string('role', 20)->default('member');
            $table->foreignUuid('registered_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Set once the invitee has an account; used for pending-approval auto-join');
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'email']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
    }
};
