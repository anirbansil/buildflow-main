<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('initial_budget', 15, 2)->nullable()->after('total_budget')
                ->comment('budget at creation, never revised — total_budget can change, this cannot');
        });

        // Backfill existing rows so the variance is 0% until total_budget is next revised.
        DB::statement('UPDATE projects SET initial_budget = total_budget WHERE initial_budget IS NULL');
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('initial_budget');
        });
    }
};
