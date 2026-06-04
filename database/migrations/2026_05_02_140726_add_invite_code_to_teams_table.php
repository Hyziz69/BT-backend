<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Team;

return new class extends Migration
{
    public function up(): void
    {
        // Add column only if it doesn't exist yet
        if (!Schema::hasColumn('teams', 'invite_code')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('invite_code', 10)->nullable()->after('name');
            });
        }

        // Generate unique invite codes for existing teams that don't have one
        Team::whereNull('invite_code')->each(function ($team) {
            $team->update(['invite_code' => strtoupper(Str::random(8))]);
        });

        // Add unique constraint only if it doesn't exist
        $hasIndex = collect(\DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name='teams_invite_code_unique'"))->count();
        if (!$hasIndex) {
            Schema::table('teams', function (Blueprint $table) {
                $table->unique('invite_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique('teams_invite_code_unique');
            $table->dropColumn('invite_code');
        });
    }
};