<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MentorApplicationController;
use App\Http\Controllers\Api\MentorCredentialController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SkillController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mentora API Routes
|--------------------------------------------------------------------------
| FR-01: Registrasi dan Login (Endpoint registrasi & autentikasi login)
| FR-02: Manajemen Profil Pengguna & Lokasi (Endpoint lihat & update profil)
| FR-03: Pengelolaan Skill (Master Skill & CRUD User Skills)
| FR-04: Pengajuan Akun Mentor (Apply, Status, & Admin Review)
| FR-05: Upload Dokumen Kredensial (Upload, Akses Terotorisasi, & Delete)
*/

// Public Endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// FR-03: Katalog Master Skill (Publik)
Route::get('/skills', [SkillController::class, 'index']);
Route::get('/skills/categories', [SkillController::class, 'categories']);

// Protected User Session Endpoints (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // FR-02: Manajemen Profil
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/location', [ProfileController::class, 'updateLocation']);

    // FR-03: CRUD Skill Pengguna (Teach & Learn)
    Route::get('/user/skills', [SkillController::class, 'userSkills']);
    Route::post('/user/skills', [SkillController::class, 'storeUserSkill']);
    Route::put('/user/skills/{id}', [SkillController::class, 'updateUserSkill']);
    Route::delete('/user/skills/{id}', [SkillController::class, 'destroyUserSkill']);

    // FR-04: Pengajuan Akun Mentor & Verifikasi Admin
    Route::post('/mentor/apply', [MentorApplicationController::class, 'apply']);
    Route::get('/mentor/status', [MentorApplicationController::class, 'status']);
    Route::get('/admin/mentor-applications', [MentorApplicationController::class, 'indexAdmin']);
    Route::post('/admin/mentor-applications/{id}/review', [MentorApplicationController::class, 'reviewAdmin']);

    // FR-05: Upload & Akses Dokumen Kredensial (Akses Hanya Pemilik & Admin)
    Route::post('/mentor/credentials', [MentorCredentialController::class, 'store']);
    Route::get('/mentor/credentials', [MentorCredentialController::class, 'index']);
    Route::get('/mentor/credentials/{id}/download', [MentorCredentialController::class, 'download']);
    Route::delete('/mentor/credentials/{id}', [MentorCredentialController::class, 'destroy']);
});
