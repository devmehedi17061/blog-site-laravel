<?php

use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostOptionsController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "options" is not read as a {post}.
Route::get('/posts/options', PostOptionsController::class)->name('posts.options');

Route::apiResource('posts', PostController::class);
