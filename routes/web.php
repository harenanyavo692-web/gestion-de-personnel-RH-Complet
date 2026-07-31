<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;

// Redirect root to bienvenue
Route::get('/', fn () => redirect()->route('bienvenue'));

// Auth routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Bienvenue page (public)
Route::get('/bienvenue', fn () => view('bienvenue'))->name('bienvenue');

// Profile routes (protected)
Route::middleware('auth')->group(function () {
    Route::get('/parametres', [ProfileController::class, 'showParametres'])->name('parametres');
    Route::post('/parametres', [ProfileController::class, 'updateParametres'])->name('parametres.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
