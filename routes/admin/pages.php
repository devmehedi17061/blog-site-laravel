<?php

use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageOptionsController;
use App\Http\Controllers\Admin\PageSectionsController;
use App\Http\Controllers\Admin\SectionTemplateController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "options" and "homepage" are not
// read as a {page}.
Route::get('/pages/options', PageOptionsController::class)->name('pages.options');
Route::get('/pages/homepage', [HomepageController::class, 'show'])->name('pages.homepage.show');
Route::put('/pages/homepage', [HomepageController::class, 'update'])->name('pages.homepage.update');

Route::get('/pages/{page}/sections', [PageSectionsController::class, 'index'])->name('pages.sections.index');
Route::put('/pages/{page}/sections', [PageSectionsController::class, 'update'])->name('pages.sections.update');
Route::post('/pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');

Route::apiResource('pages', PageController::class);

// The reusable section library, shared by every page.
Route::apiResource('section-templates', SectionTemplateController::class)
    ->parameters(['section-templates' => 'template']);
