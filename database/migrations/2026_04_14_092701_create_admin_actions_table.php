<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_id')->constrained()->cascadeOnDelete();

            $table->enum('action', ['triage', 'verifikasi', 'mitigasi', 'close']);
            $table->enum('priority', ['low', 'medium', 'high'])->nullable();

            $table->integer('sla')->nullable(); // dalam menit / jam
            $table->timestamp('action_time')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};