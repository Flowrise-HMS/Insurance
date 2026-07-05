<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claim_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scheme_code', 64);
            $table->foreignUuid('payer_id')->constrained('insurance_payers')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('batch_number', 128);
            $table->json('filter_criteria')->nullable();
            $table->unsignedSmallInteger('service_year')->nullable();
            $table->unsignedTinyInteger('service_month')->nullable();
            $table->string('status', 32)->default('generated');
            $table->unsignedInteger('claims_count')->default(0);
            $table->decimal('batch_amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('GHS');
            $table->json('master_table_versions')->nullable();
            $table->string('exported_xml_path')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'batch_number']);
            $table->index(['scheme_code', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->foreignUuid('batch_id')->nullable()->after('payer_id')->constrained('insurance_claim_batches')->nullOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->after('invoice_id')->constrained('encounters')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            $table->json('nhia_payload')->nullable()->after('metadata');

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['batch_id', 'status']);
            $table->index(['encounter_id']);
        });

        Schema::table('insurance_claim_lines', function (Blueprint $table) {
            $table->string('line_type', 32)->default('other')->after('invoice_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claim_lines', function (Blueprint $table) {
            $table->dropColumn('line_type');
        });

        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['encounter_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['batch_id', 'encounter_id', 'reviewed_at', 'reviewed_by', 'nhia_payload']);
        });

        Schema::dropIfExists('insurance_claim_batches');
    }
};
