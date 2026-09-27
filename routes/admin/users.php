<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserOptionsController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "options" is not read as a {user}.
Route::get('/users/options', UserOptionsController::class)->name('users.options');

Route::apiResource('users', UserController::class);
