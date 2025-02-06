<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_exams', function (Blueprint $table) {
            // $table->json('question_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mock_exams', function (Blueprint $table) {
            $table->dropColumn('question_order');
        });
    }
};
