<?php

use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuOptionsController;
use Illuminate\Support\Facades\Route;

// Declared before the menu routes so "options" is never read as a location.
Route::get('/menu/options', MenuOptionsController::class)->name('menu.options');

// Read and written whole: reordering touches several rows at once, so there
// is deliberately no per-item endpoint.
Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::put('/menu', [MenuController::class, 'update'])->name('menu.update');
