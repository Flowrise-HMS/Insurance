<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nhis_medicines', function (Blueprint $table) {
            $table->string('prescribing_level_code', 8)->default('A')->after('form');
            $table->string('unit_of_pricing', 64)->nullable()->after('prescribing_level');
        });

        Schema::table('provider_credentialing', function (Blueprint $table) {
            $table->string('prescribing_level_code', 8)->default('A')->after('provider_name');
        });

        $this->backfillFromLegacyOrdinal('nhis_medicines');
        $this->backfillFromLegacyOrdinal('provider_credentialing');
    }

    public function down(): void
    {
        Schema::table('nhis_medicines', function (Blueprint $table) {
            $table->dropColumn(['prescribing_level_code', 'unit_of_pricing']);
        });

        Schema::table('provider_credentialing', function (Blueprint $table) {
            $table->dropColumn('prescribing_level_code');
        });
    }

    protected function backfillFromLegacyOrdinal(string $table): void
    {
        $map = [
            1 => ['code' => 'A', 'ordinal' => 1],
            2 => ['code' => 'B2', 'ordinal' => 4],
            3 => ['code' => 'D', 'ordinal' => 6],
            4 => ['code' => 'B2', 'ordinal' => 4],
            5 => ['code' => 'C', 'ordinal' => 5],
            6 => ['code' => 'D', 'ordinal' => 6],
            7 => ['code' => 'SM', 'ordinal' => 7],
        ];

        foreach ($map as $legacyOrdinal => $target) {
            DB::table($table)
                ->where('prescribing_level', $legacyOrdinal)
                ->update([
                    'prescribing_level_code' => $target['code'],
                    'prescribing_level' => $target['ordinal'],
                ]);
        }
    }
};
