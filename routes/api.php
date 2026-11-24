<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsureApiTokenAbility;
use Modules\Core\Services\ApiRouteRegistrar;
use Modules\Insurance\Http\Controllers\Api\CatalogSyncController;
use Modules\Insurance\Http\Controllers\Api\ClaimBatchController;
use Modules\Insurance\Http\Controllers\Api\ClaimFeedbackController;
use Modules\Insurance\Http\Controllers\Api\ClaimSubmissionController;
use Modules\Insurance\Http\Controllers\Api\InsuranceClaimController;
use Modules\Insurance\Http\Controllers\Api\PatientPolicyController;
use Modules\Insurance\Http\Controllers\Api\PayerController;
use Modules\Insurance\Http\Controllers\Api\TariffItemController;

/*
| The claim-feedback endpoint is intentionally unauthenticated: NHIA posts
| adjudication responses to it. Like the billing webhook, it stays outside both
| auth and ApiRouteRegistrar so it keeps working when the Api module is disabled.
|
| The staff-facing endpoints carry `api.branch` so branch context is populated —
| none of the Insurance models extend BaseModel, so nothing is scoped without it.
*/

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'api.branch', EnsureApiTokenAbility::class.':insurance'])->group(function () {
        Route::post('insurance/catalog/sync', [CatalogSyncController::class, 'store'])->name('insurance.catalog.sync');
        Route::post('insurance/claims/submit', [ClaimSubmissionController::class, 'store'])->name('insurance.claims.submit');
    });

    Route::post('insurance/claims/feedback', [ClaimFeedbackController::class, 'store'])->name('insurance.claims.feedback');
});

/*
| Insurance REST API (`/api/v1`, Sanctum + branch, token scope "insurance").
| The legacy insurance/* endpoints above stay for existing integrations.
*/
ApiRouteRegistrar::register(scope: 'insurance', routes: function (): void {
    PayerController::routes('payers');
    PatientPolicyController::routes('patient-policies', ['index', 'show', 'store', 'update', 'destroy'], ['verify' => 'verify']);
    InsuranceClaimController::routes('claims', ['index', 'show', 'store'], ['submit' => 'submit']);
    ClaimBatchController::routes('claim-batches', ['index', 'show']);
    TariffItemController::routes('tariff-items', ['index', 'show']);
});
