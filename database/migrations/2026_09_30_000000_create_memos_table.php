<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('stable_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('memos');
            $table->string('disk_path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('status')->index();
            $table->longText('transcript')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['stable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
