<?php

use App\Http\Controllers\Q1Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/applications/{type?}', [Q1Controller::class,'applications'])->name('applications');
});