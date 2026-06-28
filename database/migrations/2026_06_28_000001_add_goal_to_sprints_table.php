<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sprint goal — the short "what we want to achieve this sprint" note
     * shown on the Sprints page and collected by the New/Edit sprint modal.
     */
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->text('goal')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropColumn('goal');
        });
    }
};
