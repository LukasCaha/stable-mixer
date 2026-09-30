<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->string('language')->default('en');
        });
    }

    public function down(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
