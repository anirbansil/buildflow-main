<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contractor_name');
            $table->string('contact_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('work_package')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->boolean('is_retainage')->default(false);
            $table->tinyInteger('status')->default(1)
                ->comment('1=Pending Approval,2=Approved,3=Cleared,4=Rejected');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
