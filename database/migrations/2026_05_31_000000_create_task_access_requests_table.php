<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // The user asking for access.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message')->nullable();
            $table->enum('status', ['pending', 'approved', 'declined'])->default('pending');
            // Owner/admin who approved or declined.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // One request row per (task, user). Re-requesting after a decline
            // flips the same row back to pending via updateOrCreate.
            $table->unique(['task_id', 'user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_access_requests');
    }
};
