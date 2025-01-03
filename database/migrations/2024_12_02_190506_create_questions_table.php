<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->text('question');
            $table->string('image_url')->nullable();
            $table->text('solution');
            $table->foreignId('section_id')->index()->constrained('sections');
            $table->foreignId('subject_id')->index()->constrained('subjects');
            $table->foreignId('chapter_id')->index()->constrained('chapters');
            $table->foreignId('topic_id')->index()->constrained('topics'); // FK to topics
            $table->foreignId('objective_id')->index()->constrained('objectives'); // FK to objectives
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
