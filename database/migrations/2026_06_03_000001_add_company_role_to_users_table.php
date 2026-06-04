<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Company-internal role. Null = not part of any company management structure.
            // owner   – created the company, full control (invite/kick/assign roles)
            // manager – elevated rights granted by owner (manage challenges & applications)
            // member  – basic company worker
            // Kept as a plain string (not enum) to avoid SQLite CHECK-constraint pain.
            if (!Schema::hasColumn('users', 'company_role')) {
                $table->string('company_role', 20)->nullable()->after('company_id');
                $table->index('company_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_role']);
            $table->dropColumn('company_role');
        });
    }
};
