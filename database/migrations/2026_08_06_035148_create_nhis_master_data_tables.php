<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_books', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branch_tariff_book', function (Blueprint $table) {
            $table->uuid('branch_id');
            $table->uuid('tariff_book_id');
            $table->primary(['branch_id', 'tariff_book_id']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('tariff_book_id')->references('id')->on('tariff_books')->cascadeOnDelete();
        });

        Schema::table('insurance_tariff_items', function (Blueprint $table) {
            $table->foreignUuid('tariff_book_id')->nullable()->after('payer_id')->constrained('tariff_books')->nullOnDelete();
            $table->date('effective_from')->nullable()->after('source_updated_at');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->string('admission_type', 8)->nullable()->after('effective_to');
        });

        Schema::create('insurance_gdrg_icd_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('icd10_code', 32);
            $table->string('gdrg_code', 64);
            $table->string('description', 255)->nullable();
            $table->string('mdc', 16)->nullable();
            $table->string('service_type', 8)->default('OUT');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source_file', 255)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['icd10_code', 'gdrg_code', 'service_type'], 'gdrg_icd_unique');
            $table->index('gdrg_code');
        });

        Schema::create('nhis_medicines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->string('strength', 64)->nullable();
            $table->string('form', 64)->nullable();
            $table->unsignedTinyInteger('prescribing_level')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source_file', 255)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['name', 'is_active']);
        });

        Schema::create('members_master', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('member_number', 64);
            $table->string('card_serial_number', 32);
            $table->string('first_name', 128)->nullable();
            $table->string('last_name', 128)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 8)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source_file', 255)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['member_number', 'card_serial_number'], 'members_master_unique');
            $table->index(['member_number', 'is_active']);
            $table->index(['card_serial_number', 'is_active']);
        });

        Schema::create('provider_credentialing', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('provider_name', 255)->nullable();
            $table->unsignedTinyInteger('prescribing_level')->default(1);
            $table->json('specialities')->nullable();
            $table->string('accreditation_number', 128)->nullable();
            $table->string('level_of_care', 64)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source_file', 255)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'is_active']);
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_credentialing');
        Schema::dropIfExists('members_master');
        Schema::dropIfExists('nhis_medicines');
        Schema::dropIfExists('insurance_gdrg_icd_maps');

        Schema::table('insurance_tariff_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tariff_book_id');
            $table->dropColumn(['effective_from', 'effective_to', 'admission_type']);
        });

        Schema::dropIfExists('branch_tariff_book');
        Schema::dropIfExists('tariff_books');
    }
};
