<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->time('deadline_time')->nullable()->after('deadline');
            $table->string('phase_label')->nullable()->after('priority')
                ->comment('free text, e.g. "Phase 2: Rough-in"');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['deadline_time', 'phase_label']);
        });
    }
};
