<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_generating_percentages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('section_code')->nullable();
            $table->foreignId('objective_id')->nullable()->constrained()->nullOnDelete();
            $table->string('objective_code')->nullable();
            $table->year('year')->nullable();
            $table->decimal('percentage_value', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_generating_percentages');
    }
};
