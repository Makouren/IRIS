<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ranking_bodies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('short_name', 20)->unique();
        });

        Schema::create('rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_body_id')->constrained('ranking_bodies')->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->string('category', 100)->nullable();
            $table->string('global_rank', 50)->nullable();
            $table->integer('rank_value')->nullable();
            $table->string('ph_rank', 50)->nullable();
            $table->integer('ph_rank_value')->nullable();
            $table->string('note', 255)->nullable();
        });

        Schema::create('ranking_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_body_id')->constrained('ranking_bodies')->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->string('group_label', 100)->nullable();
            $table->string('item_label', 200);
            $table->string('rank_display', 50)->nullable();
            $table->integer('rank_value')->nullable();
            $table->string('note', 255)->nullable();
        });

        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('short_code', 20);
            $table->decimal('contribution_percent', 5, 2);
            $table->unsignedInteger('year');
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('college_id')->constrained('colleges')->cascadeOnDelete();
            $table->unsignedInteger('national_rank');
            $table->decimal('score', 5, 2);
            $table->integer('movement')->default(0);
            $table->unsignedInteger('year');
        });

        Schema::create('accreditations', function (Blueprint $table) {
            $table->id();
            $table->string('program_name', 150);
            $table->string('accrediting_body', 100)->default('AUN-QA');
            $table->unsignedInteger('year');
            $table->string('assessment_date', 100)->nullable();
            $table->string('criterion', 150);
            $table->string('score', 50)->nullable();
            $table->decimal('numeric_score', 4, 2)->nullable();
        });

        Schema::create('uploads_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('file_type', 10)->nullable();
            $table->string('upload_type', 50);
            $table->integer('rows_inserted')->default(0);
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads_log');
        Schema::dropIfExists('accreditations');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('colleges');
        Schema::dropIfExists('ranking_breakdowns');
        Schema::dropIfExists('rankings');
        Schema::dropIfExists('ranking_bodies');
        Schema::dropIfExists('users');
    }
};
