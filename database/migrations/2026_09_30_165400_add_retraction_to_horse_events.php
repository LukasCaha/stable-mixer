<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horse_events', function (Blueprint $table) {
            $table->timestamp('retracted_at')->nullable();
            $table->foreignUuid('retracted_by_memo_id')->nullable()->constrained('memos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('horse_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retracted_by_memo_id');
            $table->dropColumn('retracted_at');
        });
    }
};
