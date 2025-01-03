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
        Schema::create('mock_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->index()->constrained('subjects');
            $table->foreignId('mock_exam_id')->index();
            $table->foreignId('question_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_exam_questions');
    }
};
