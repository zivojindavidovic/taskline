<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('invited_by');
            $table->timestamp('expires_at')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'expires_at']);
        });
    }
};
