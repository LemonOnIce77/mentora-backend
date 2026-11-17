<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\MatchingController;
use App\Http\Controllers\Api\MentorApplicationController;
use App\Http\Controllers\Api\MentorCredentialController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SesiExchangeController;
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
| FR-06: Verifikasi Mentor oleh Admin (List, Detail, Review)
| FR-08: Skill Matching (Two-way Match, Skor Kecocokan, Kasus Tepi)
| FR-09: Pencarian Partner Belajar & Nearby Peer-Finder (Haversine Formula)
| FR-10: Chat Room Real-time (Pesan, Riwayat, Otorisasi Akses Privat)
| FR-11: Pengaturan Jadwal Sesi (CRUD Jadwal Ketersediaan, Anti-Overlap, Lihat Jadwal Mitra)
| FR-12: Reservasi Sesi Belajar Anti-Bentrok (Booking, Anti-Collision, Pembatalan Sesi)
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

    // FR-04 & FR-06: Pengajuan Akun Mentor & Verifikasi Admin
    Route::post('/mentor/apply', [MentorApplicationController::class, 'apply']);
    Route::get('/mentor/status', [MentorApplicationController::class, 'status']);
    Route::get('/admin/mentor-applications', [MentorApplicationController::class, 'indexAdmin']);
    Route::get('/admin/mentor-applications/{id}', [MentorApplicationController::class, 'showAdmin']);
    Route::post('/admin/mentor-applications/{id}/review', [MentorApplicationController::class, 'reviewAdmin']);

    // FR-05: Upload & Akses Dokumen Kredensial (Akses Hanya Pemilik & Admin)
    Route::post('/mentor/credentials', [MentorCredentialController::class, 'store']);
    Route::get('/mentor/credentials', [MentorCredentialController::class, 'index']);
    Route::get('/mentor/credentials/{id}/download', [MentorCredentialController::class, 'download']);
    Route::delete('/mentor/credentials/{id}', [MentorCredentialController::class, 'destroy']);

    // FR-08: Skill Matching
    Route::get('/matching', [MatchingController::class, 'index']);

    // FR-09: Pencarian Partner Belajar & Nearby Peer-Finder
    Route::get('/peers/search', [MatchingController::class, 'search']);
    Route::get('/peers/nearby', [MatchingController::class, 'nearby']);

    // FR-10: Chat Room Real-time & Riwayat Percakapan
    Route::get('/chat/rooms', [ChatController::class, 'getRooms']);
    Route::get('/chat/messages', [ChatController::class, 'getMessages']);
    Route::post('/chat/messages', [ChatController::class, 'sendMessage']);

    // FR-11: Pengaturan Jadwal Sesi
    Route::get('/schedules', [JadwalController::class, 'index']);
    Route::post('/schedules', [JadwalController::class, 'store']);
    Route::put('/schedules/{id}', [JadwalController::class, 'update']);
    Route::delete('/schedules/{id}', [JadwalController::class, 'destroy']);
    Route::get('/schedules/user/{userId}', [JadwalController::class, 'viewPartnerSchedules']);

    // FR-12: Reservasi Sesi Belajar Anti-Bentrok
    Route::post('/sessions/book', [SesiExchangeController::class, 'book']);
    Route::get('/sessions', [SesiExchangeController::class, 'index']);
    Route::get('/sessions/{id}', [SesiExchangeController::class, 'show']);
    Route::post('/sessions/{id}/cancel', [SesiExchangeController::class, 'cancel']);
});
