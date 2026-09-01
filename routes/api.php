<?php

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Http\Controllers\Api\CatalogSyncController;
use Modules\Insurance\Http\Controllers\Api\ClaimFeedbackController;
use Modules\Insurance\Http\Controllers\Api\ClaimSubmissionController;

/*
| The claim-feedback endpoint is intentionally unauthenticated: NHIA posts
| adjudication responses to it. Like the billing webhook, it stays outside both
| auth and ApiRouteRegistrar so it keeps working when the Api module is disabled.
|
| The staff-facing endpoints carry `api.branch` so branch context is populated —
| none of the Insurance models extend BaseModel, so nothing is scoped without it.
*/

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'api.branch'])->group(function () {
        Route::post('insurance/catalog/sync', [CatalogSyncController::class, 'store'])->name('insurance.catalog.sync');
        Route::post('insurance/claims/submit', [ClaimSubmissionController::class, 'store'])->name('insurance.claims.submit');
    });

    Route::post('insurance/claims/feedback', [ClaimFeedbackController::class, 'store'])->name('insurance.claims.feedback');
});
