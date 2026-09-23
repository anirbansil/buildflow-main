<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity', 15, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->text('worker_description')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('supervisor_comment')->nullable();
            $table->timestamp('supervisor_reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('owner_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->tinyInteger('status')->default(1)
                ->comment('1=Pending,2=In Review,3=Pending Final Approval,4=Revision Requested,5=Rejected,6=Approved');
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('submission_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_media');
        Schema::dropIfExists('submissions');
    }
};
