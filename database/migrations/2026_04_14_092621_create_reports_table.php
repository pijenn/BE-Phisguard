<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            $table->string('ticket')->unique();

            $table->string('channel_chat');
            $table->string('sender_account');
            $table->text('chat_text');

            $table->string('url')->nullable();
            $table->string('reporter_name')->nullable();
            $table->string('region')->nullable();
            $table->string('modus_type')->nullable();
            $table->text('evidence_text')->nullable();
            $table->string('user_segment')->nullable();
            $table->text('incident_summary')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};