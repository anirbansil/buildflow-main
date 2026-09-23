<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('retainage_rate', 5, 2)->default(10)->after('used_budget')->comment('percent, e.g. 10.00 = 10%');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('retainage_rate');
        });
    }
};
