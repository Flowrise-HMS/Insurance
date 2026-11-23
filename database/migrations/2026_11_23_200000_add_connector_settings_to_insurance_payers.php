<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-payer claims connector: which connector to use and its (encrypted)
 * settings, kept apart from the free-form `config` key/value field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_payers', function (Blueprint $table) {
            $table->string('connector_code')->nullable()->after('config');
            $table->text('connector_config')->nullable()->after('connector_code');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_payers', function (Blueprint $table) {
            $table->dropColumn(['connector_code', 'connector_config']);
        });
    }
};
