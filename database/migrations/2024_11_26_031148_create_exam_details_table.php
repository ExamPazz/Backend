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
        Schema::create('exam_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('exam_name', ['JAMB', 'WAEC', 'NECO']);            
            $table->string('registration_number')->nullable();
            $table->boolean('has_written_before');
            $table->year('exam_year')->nullable();
            $table->integer('previous_score')->nullable();
            $table->integer('target_score')->nullable();
            $table->json('subject_combinations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_details');
    }
};
