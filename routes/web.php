<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;

// Redirect root to bienvenue
Route::get('/', fn () => redirect()->route('bienvenue'));

// Auth routes
// Route::get('/login', 'App\\Http\\Controllers\\AuthController@showLogin')->name('login')->middleware('guest');
Route::post('/login', 'App\\Http\\Controllers\\AuthController@login')->middleware('guest');
Route::get('/register', 'App\\Http\\Controllers\\AuthController@showRegister')->name('register')->middleware('guest');
Route::post('/register', 'App\\Http\\Controllers\\AuthController@register')->middleware('guest');
Route::post('/logout', 'App\\Http\\Controllers\\AuthController@logout')->name('logout')->middleware('auth');

// Bienvenue page (public)
Route::get('/bienvenue', fn () => view('bienvenue'))->name('bienvenue');

// Profile routes (protected)
Route::middleware('auth')->group(function () {
    Route::get('/parametres', [ProfileController::class, 'showParametres'])->name('parametres');
    Route::post('/parametres', [ProfileController::class, 'updateParametres'])->name('parametres.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
