<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 25)->default('postulante')->index();
            $table->string('document_number', 20)->nullable()->unique();
            $table->string('phone', 30)->nullable();
        });
        Schema::create('recruitment_calls', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('modality')->default('NOMBRAMIENTO');
            $table->string('status')->default('BORRADOR')->index();
            $table->boolean('is_demo')->default(false);
            $table->boolean('rules_approved')->default(false);
            $table->string('resolution')->nullable();
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->json('schedule');
            $table->json('rubric');
            $table->json('public_documents')->nullable();
            $table->dateTime('results_published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_call_id')->constrained()->restrictOnDelete();
            $table->string('faculty');
            $table->string('school');
            $table->string('airhsp', 30);
            $table->string('cap', 30);
            $table->string('category')->default('AUXILIAR');
            $table->string('dedication')->default('TIEMPO COMPLETO');
            $table->unsignedSmallInteger('weekly_hours')->default(40);
            $table->json('courses');
            $table->json('requirements');
            $table->timestamps();
            $table->unique(['recruitment_call_id', 'airhsp']);
        });
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('recruitment_call_id')->constrained()->restrictOnDelete();
            $table->foreignId('vacancy_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('BORRADOR')->index();
            $table->text('statement')->nullable();
            $table->json('applicant_snapshot')->nullable();
            $table->dateTime('consented_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->string('receipt_hash', 64)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'recruitment_call_id']);
        });
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('requirement_key');
            $table->string('name');
            $table->string('path');
            $table->string('mime');
            $table->unsignedInteger('size');
            $table->string('sha256', 64);
            $table->string('review_status')->default('PENDIENTE');
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'requirement_key']);
        });
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('scores');
            $table->decimal('total', 7, 3);
            $table->text('notes');
            $table->dateTime('closed_at');
            $table->timestamps();
            $table->unique(['application_id', 'user_id']);
        });
        Schema::create('jury_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unique(['vacancy_id', 'user_id']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'jury_assignments', 'evaluations', 'application_documents', 'applications', 'vacancies', 'recruitment_calls'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'document_number', 'phone']);
        });
    }
};
