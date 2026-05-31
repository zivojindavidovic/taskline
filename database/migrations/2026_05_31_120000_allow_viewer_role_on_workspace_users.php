<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The workspace member UI and validation (InviteMemberRequest,
     * WorkspaceMembersController) already offer a "viewer" role, but the
     * original workspace_users.role enum only allowed owner/admin/member,
     * so assigning a viewer threw a CHECK-constraint violation. Relax the
     * column to a plain string — the request validation (`in:admin,member,
     * viewer`) is the source of truth for allowed values.
     */
    public function up(): void
    {
        Schema::table('workspace_users', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->change();
        });
    }

    public function down(): void
    {
        // Collapse any viewers back to member so the stricter enum can apply.
        \DB::table('workspace_users')->where('role', 'viewer')->update(['role' => 'member']);

        Schema::table('workspace_users', function (Blueprint $table) {
            $table->enum('role', ['owner', 'admin', 'member'])->default('member')->change();
        });
    }
};
