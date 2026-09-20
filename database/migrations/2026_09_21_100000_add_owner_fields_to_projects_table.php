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
            $table->tinyInteger('phase')->default(1)->after('status')
                ->comment('1=Planning,2=In Progress,3=Delayed,4=Finishing,5=Completed');
            $table->string('category')->nullable()->after('phase')
                ->comment('commercial,infrastructure,residential');
            $table->unsignedTinyInteger('progress')->default(0)->after('category')->comment('0-100');
            $table->string('project_code')->nullable()->unique()->after('progress');
            $table->string('general_contractor')->nullable()->after('project_code');
            $table->string('lead_architect')->nullable()->after('general_contractor');
            $table->decimal('used_budget', 15, 2)->default(0)->after('total_budget');
        });

        DB::statement("UPDATE projects SET project_code = CONCAT('PRJ-', YEAR(created_at), '-', LPAD(id, 3, '0')) WHERE project_code IS NULL AND created_at IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['project_code']);
            $table->dropColumn([
                'phase', 'category', 'progress', 'project_code',
                'general_contractor', 'lead_architect', 'used_budget',
            ]);
        });
    }
};
