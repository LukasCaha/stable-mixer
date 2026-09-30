<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('stable_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('memo_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->timestamps();

            $table->index(['stable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
