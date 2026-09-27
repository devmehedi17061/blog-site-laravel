<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoleOptionsController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "options" is not read as a {role}.
Route::get('/roles/options', RoleOptionsController::class)->name('roles.options');

Route::apiResource('roles', RoleController::class);
