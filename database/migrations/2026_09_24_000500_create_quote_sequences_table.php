<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-year quote-number sequence (Architecture.md 5.2, AD-4). A dedicated
     * table gives gapless numbering without Redis, which shared hosting
     * cannot provide. The unique index on quotes.quote_number is the final
     * safety net.
     */
    public function up(): void
    {
        Schema::create('quote_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_sequences');
    }
};
