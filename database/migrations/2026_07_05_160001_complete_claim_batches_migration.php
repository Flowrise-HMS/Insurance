<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('insurance_claim_batches') && ! Schema::hasColumn('insurance_claims', 'batch_id')) {
            Schema::table('insurance_claim_batches', function (Blueprint $table) {
                if (! $this->indexExists('insurance_claim_batches', 'insurance_claim_batches_branch_id_batch_number_unique')) {
                    $table->unique(['branch_id', 'batch_number']);
                }
                if (! $this->indexExists('insurance_claim_batches', 'insurance_claim_batches_scheme_code_status_index')) {
                    $table->index(['scheme_code', 'status']);
                }
            });

            if (Schema::hasColumn('insurance_claim_batches', 'created_by')) {
                Schema::table('insurance_claim_batches', function (Blueprint $table) {
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                });
            }
        }

        if (! Schema::hasColumn('insurance_claims', 'batch_id')) {
            Schema::table('insurance_claims', function (Blueprint $table) {
                $table->foreignUuid('batch_id')->nullable()->after('payer_id')->constrained('insurance_claim_batches')->nullOnDelete();
                $table->foreignUuid('encounter_id')->nullable()->after('invoice_id')->constrained('encounters')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
                $table->json('nhia_payload')->nullable()->after('metadata');

                $table->index(['batch_id', 'status']);
                $table->index(['encounter_id']);
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('insurance_claim_lines', 'line_type')) {
            Schema::table('insurance_claim_lines', function (Blueprint $table) {
                $table->string('line_type', 32)->default('other')->after('invoice_line_id');
            });
        }
    }

    public function down(): void
    {
        // Repair migration — no down.
    }

    protected function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) AS count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return (int) ($result[0]->count ?? 0) > 0;
    }
};
