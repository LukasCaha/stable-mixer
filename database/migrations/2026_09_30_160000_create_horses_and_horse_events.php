<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            $table->string('log_status')->default('pending')->index();
            $table->text('log_error')->nullable();
        });

        Schema::create('horses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->json('aliases');
            $table->longText('knowledge');
            $table->timestamps();

            $table->unique(['stable_id', 'name_key']);
        });

        Schema::create('horse_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('memo_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on')->nullable();
            $table->text('summary');
            $table->text('detail');
            $table->timestamps();

            $table->index(['horse_id', 'occurred_on']);
            $table->index('memo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_events');
        Schema::dropIfExists('horses');

        Schema::table('memos', function (Blueprint $table) {
            $table->dropColumn(['log_status', 'log_error']);
        });
    }
};
