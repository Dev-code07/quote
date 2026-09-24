        <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template editor additions from docs/quoteflow_template_editor.html step 4:
 * an optional company stamp image, and the "use a generated seal when no stamp
 * is uploaded" fallback checkbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_templates', function (Blueprint $table): void {
            $table->string('company_stamp_path')->nullable()->after('signature_path');
            $table->boolean('use_generated_seal')->default(true)->after('company_stamp_path');
        });
    }

    public function down(): void
    {
        Schema::table('quote_templates', function (Blueprint $table): void {
            $table->dropColumn(['company_stamp_path', 'use_generated_seal']);
        });
    }
};
