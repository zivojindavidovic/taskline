<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            // Null means "all projects in the workspace" (legacy behavior for invites
            // created before this column existed). An empty array means "no access".
            $table->json('projects')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->dropColumn('projects');
        });
    }
};
