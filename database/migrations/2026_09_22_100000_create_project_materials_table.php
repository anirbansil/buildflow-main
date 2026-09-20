<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 50);
            $table->decimal('estimated_qty', 15, 2)->default(0);
            $table->decimal('delivered_qty', 15, 2)->default(0);
            $table->tinyInteger('status')->default(1)->comment('1=On Track,2=Delayed,3=Delivered');
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->text('site_info')->nullable()->after('lead_architect');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('site_info');
        });

        Schema::dropIfExists('project_materials');
    }
};
