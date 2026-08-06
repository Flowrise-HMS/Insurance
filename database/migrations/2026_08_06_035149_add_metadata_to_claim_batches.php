<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_claim_batches', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('master_table_versions');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claim_batches', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
