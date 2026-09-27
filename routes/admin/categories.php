<?php

use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "order" is never read as a {category}.
Route::put('/categories/order', [CategoryController::class, 'reorder'])->name('categories.reorder');

Route::apiResource('categories', CategoryController::class);
