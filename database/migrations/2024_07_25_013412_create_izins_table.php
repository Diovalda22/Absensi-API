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
        Schema::create('izin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('image_id');
            $table->date('tanggal');
            $table->enum('keterangan', ['sakit', 'izin']);
            $table->string('deskripsi');
            $table->enum('status', ['pending', 'approve'])->default('pending');

            $table->foreign('siswa_id')->references('id')->on('siswa')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('image_id')->references('id')->on('images')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('izins');
    }
};
