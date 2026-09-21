<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\SummaryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (session()->has('user_id')) {
        return redirect()->route(session('role') === 'admin' ? 'admin.dashboard' : 'user.dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.session')->name('logout');

Route::middleware('auth.session')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('user.dashboard');
    Route::get('/api/summary', [SummaryController::class, 'index'])->name('api.summary');
});

Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
    Route::post('/upload-csv', [UploadController::class, 'csv'])->name('upload.csv');
    Route::get('/smart-upload', [UploadController::class, 'showSmartUpload'])->name('smart-upload');
    Route::post('/smart-upload', [UploadController::class, 'smartUpload'])->name('smart-upload.process');
    Route::get('/review-extraction', [UploadController::class, 'reviewExtraction'])->name('review-extraction');
    Route::post('/review-extraction/confirm', [UploadController::class, 'confirmExtraction'])->name('review-extraction.confirm');
});
