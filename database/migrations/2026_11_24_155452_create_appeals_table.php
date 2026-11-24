<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // reconsideracion | apelacion
            $table->string('stage', 20); // aptitude | external
            $table->text('grounds');
            $table->text('evidence')->nullable();
            $table->string('status', 20)->default('PENDIENTE')->index(); // PENDIENTE | RESUELTA | RECHAZADA
            $table->text('decision')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'type', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
