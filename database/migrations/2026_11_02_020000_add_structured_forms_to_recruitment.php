<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->json('screening_rules')->nullable();
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->json('form_data')->nullable();
            $table->json('screening_snapshot')->nullable();
            $table->decimal('declared_score', 8, 3)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['form_data', 'screening_snapshot', 'declared_score']);
        });
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn('screening_rules');
        });
    }
};
