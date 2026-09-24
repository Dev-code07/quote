<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Columns follow docs/Architecture.md section 4.3. A quote copies these
     * values into its own snapshot on save, so this table may change freely
     * without touching historical quotes (BR-01).
     */
    public function up(): void
    {
        Schema::create('quote_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);

            // Branding (decision #10)
            $table->string('accent_color', 20)->default('navy');
            $table->string('header_alignment', 20)->default('center');
            $table->string('letterhead_display_name')->nullable();
            $table->string('doc_title')->default('QUOTATION');
            $table->string('company_name');
            $table->string('company_gstin', 15)->nullable();
            $table->string('tagline')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_1')->nullable();
            $table->string('mobile_2')->nullable();
            $table->string('stamp_place')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('authorized_person')->nullable();
            $table->string('designation')->nullable();

            // Quote defaults
            $table->decimal('default_gst_rate', 5, 2)->default(18);
            $table->text('intro_message')->nullable();
            $table->string('delivery_period')->nullable();
            $table->string('warranty')->nullable();
            $table->string('validity_text')->nullable();
            $table->text('extra_terms')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_templates');
    }
};
