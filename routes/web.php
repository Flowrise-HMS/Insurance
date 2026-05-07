<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('insurance', 'insurance::index')->name('insurance.index');
});
