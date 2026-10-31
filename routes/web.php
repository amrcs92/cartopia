<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'WelcomePage')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'DashboardPage')->name('dashboard');
});

require __DIR__.'/settings.php';
