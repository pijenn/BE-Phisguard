<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ml_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_id')->constrained()->cascadeOnDelete();

            $table->enum('label', ['phishing', 'non-phishing']);
            $table->integer('risk_score'); // 0 - 100
            $table->enum('priority', ['low', 'medium', 'high']);
            $table->text('reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_results');
    }
};