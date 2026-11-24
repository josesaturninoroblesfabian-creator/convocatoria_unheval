<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jury_assignments', function (Blueprint $table) {
            $table->string('role', 20)->default('titular')->after('user_id');
        });

        Schema::create('jury_recusals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('juror_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('SOLICITADA'); // SOLICITADA | APROBADA | RECHAZADA
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['vacancy_id', 'juror_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jury_recusals');
        Schema::table('jury_assignments', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
