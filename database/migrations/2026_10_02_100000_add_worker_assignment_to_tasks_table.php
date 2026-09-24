<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_code')->nullable()->unique()->after('id');
            $table->tinyInteger('priority')->default(2)->after('is_critical_path')->comment('1=Low,2=Medium,3=High');
            $table->foreignId('assigned_worker_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->foreignId('worker_assigned_by')->nullable()->after('assigned_worker_id')->constrained('users')->nullOnDelete();
            $table->timestamp('worker_assigned_at')->nullable()->after('worker_assigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['task_code', 'priority', 'assigned_worker_id', 'worker_assigned_by', 'worker_assigned_at']);
        });
    }
};
