<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Columns follow docs/Architecture.md section 4.4. The *_snapshot JSON
     * columns are what make historical quotes immutable (BR-01): a quote
     * prints from its own copy, never from the live client or template.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            // BR-03: unique, never reused, server generated.
            $table->string('quote_number', 20)->unique();

            $table->date('quote_date');
            $table->date('valid_until');

            // Optional (decision #9); feeds the intro-message tokens.
            $table->string('enquiry_no')->nullable();
            $table->date('enquiry_date')->nullable();

            $table->string('status', 20)->default('draft');

            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('quote_templates')->nullOnDelete();

            $table->json('client_snapshot')->nullable();
            $table->json('template_snapshot')->nullable();
            $table->json('terms')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('amount_in_words')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('quote_date');
            $table->index('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
