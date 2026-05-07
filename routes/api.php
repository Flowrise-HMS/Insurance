<?php

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Http\Controllers\Api\CatalogSyncController;
use Modules\Insurance\Http\Controllers\Api\ClaimFeedbackController;
use Modules\Insurance\Http\Controllers\Api\ClaimSubmissionController;

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('insurance/catalog/sync', [CatalogSyncController::class, 'store'])->name('insurance.catalog.sync');
        Route::post('insurance/claims/submit', [ClaimSubmissionController::class, 'store'])->name('insurance.claims.submit');
    });

    Route::post('insurance/claims/feedback', [ClaimFeedbackController::class, 'store'])->name('insurance.claims.feedback');
});
