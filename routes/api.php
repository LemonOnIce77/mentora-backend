<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mentora API Routes
|--------------------------------------------------------------------------
| FR-01: Registrasi dan Login (Endpoint registrasi & autentikasi login)
| FR-02: Manajemen Profil Pengguna & Lokasi (Endpoint lihat & update profil)
*/

// Public Authentication Endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected User Session Endpoints (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // FR-02: Manajemen Profil
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/location', [ProfileController::class, 'updateLocation']);
});
