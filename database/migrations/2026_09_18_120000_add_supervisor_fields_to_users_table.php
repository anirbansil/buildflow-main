<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('title')->nullable()->after('profile_photo');
            $table->unsignedSmallInteger('experience_years')->nullable()->after('title');
            $table->unsignedTinyInteger('performance_score')->nullable()->after('experience_years')
                ->comment('0-100');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['title', 'experience_years', 'performance_score']);
        });
    }
};
