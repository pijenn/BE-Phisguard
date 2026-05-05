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
        Schema::create('respons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->enum('hasil_keputusan', ['Confirm Valid Phishing', 'False Positive', 'Need More Info']);
            $table->enum('kategori', [
                'Mengatasnamakan Bank',
                'E-Wallet / Fintech',
                'OTP / Verifikasi Akun',
                'Hadiah / Undian',
                'Paket / Kurir',
                'Customer Service Palsu',
                'Investasi Bodong',
                'Akun Marketplace',
                'Typosquatting / Domain Palsu',
                'Lainnya'
            ])->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->onDelete('cascade');
            $table->index('hasil_keputusan');
            $table->index('report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('respons');
    }
};
