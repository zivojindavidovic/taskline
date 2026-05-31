<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * URL-exposed resources get a UUIDv7 surrogate used for route binding, so the
 * sequential integer primary key is never shown in (or enumerable from) a URL.
 * The integer id remains the internal key. Existing rows are backfilled.
 */
return new class extends Migration
{
    private array $tables = ['projects', 'tasks', 'sprints', 'board_columns'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Add nullable first so the backfill can populate existing rows
            // before we enforce the unique constraint.
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('uuid')->nullable()->after('id');
            });

            DB::table($table)->whereNull('uuid')->orderBy('id')->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update([
                    'uuid' => (string) Str::uuid7(),
                ]);
            });

            Schema::table($table, function (Blueprint $t) {
                $t->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique($this->indexName($table));
                $t->dropColumn('uuid');
            });
        }
    }

    private function indexName(string $table): string
    {
        return $table.'_uuid_unique';
    }
};
