<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recruitment_calls', function (Blueprint $table) {
            $table->json('evaluation_template')->nullable();
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->json('external_evaluation')->nullable();
            $table->json('evaluation_outcome')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['external_evaluation', 'evaluation_outcome']);
        });
        Schema::table('recruitment_calls', function (Blueprint $table) {
            $table->dropColumn('evaluation_template');
        });
    }
};
