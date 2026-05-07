<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_payers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('type', 32); // nhis | private
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_patient_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payer_id')->constrained('insurance_payers')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('member_number', 128);
            $table->string('plan_code', 64)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'is_active']);
            $table->index(['payer_id', 'member_number']);
        });

        Schema::create('insurance_tariff_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payer_id')->constrained('insurance_payers')->cascadeOnDelete();
            $table->string('item_type', 32); // medication|service
            $table->string('external_code', 128);
            $table->string('name', 255);
            $table->decimal('price', 14, 2);
            $table->char('currency', 3)->default('GHS');
            $table->string('source_version', 64)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['payer_id', 'item_type', 'external_code'], 'insurance_tariff_unique');
            $table->index(['payer_id', 'is_active']);
        });

        Schema::create('insurance_catalog_syncs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payer_id')->constrained('insurance_payers')->cascadeOnDelete();
            $table->string('sync_type', 32); // medication|service|coverage
            $table->string('status', 32)->default('success');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('watermark', 255)->nullable();
            $table->unsignedInteger('records_processed')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payer_id')->constrained('insurance_payers')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->nullable()->constrained('insurance_patient_policies')->nullOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('claim_number', 128)->unique();
            $table->string('status', 32)->default('draft');
            $table->decimal('total_billed_amount', 14, 2)->default(0);
            $table->decimal('total_approved_amount', 14, 2)->default(0);
            $table->decimal('total_rejected_amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('GHS');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payer_id', 'status']);
            $table->index(['patient_id', 'status']);
        });

        Schema::create('insurance_claim_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $table->foreignUuid('invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->string('external_item_code', 128)->nullable();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('billed_amount', 14, 2);
            $table->decimal('approved_amount', 14, 2)->default(0);
            $table->decimal('rejected_amount', 14, 2)->default(0);
            $table->string('decision_status', 32)->default('pending');
            $table->string('rejection_class', 32)->nullable();
            $table->string('rejection_code', 64)->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['claim_id', 'decision_status']);
        });

        Schema::create('insurance_claim_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $table->string('connector_code', 64);
            $table->string('submission_status', 32)->default('queued');
            $table->string('idempotency_key', 128)->unique();
            $table->string('external_reference', 128)->nullable()->index();
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_claim_feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $table->foreignUuid('claim_submission_id')->nullable()->constrained('insurance_claim_submissions')->nullOnDelete();
            $table->string('external_reference', 128)->nullable()->index();
            $table->string('feedback_hash', 128)->unique();
            $table->string('feedback_type', 32)->default('status');
            $table->string('decision_status', 32)->default('pending');
            $table->string('rejection_class', 32)->nullable();
            $table->string('rejection_code', 64)->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->longText('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_feedbacks');
        Schema::dropIfExists('insurance_claim_submissions');
        Schema::dropIfExists('insurance_claim_lines');
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('insurance_catalog_syncs');
        Schema::dropIfExists('insurance_tariff_items');
        Schema::dropIfExists('insurance_patient_policies');
        Schema::dropIfExists('insurance_payers');
    }
};
