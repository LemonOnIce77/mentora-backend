<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * SCRUM-90: [FR-01][BE] Membuat endpoint registrasi
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar. Silakan gunakan email lain atau login.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create user with default role Mentee and hashed password (bcrypt)
        $user = User::create([
            'nama_lengkap' => trim($request->nama_lengkap),
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'role' => 'Mentee',
            'status_mentor' => 'NONE',
            'total_poin' => 0,
            'onboarding_completed' => false,
        ]);

        // Generate API token
        $token = $user->createToken('mentora_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi akun berhasil!',
            'user' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'email' => $user->email,
                'role' => $user->role,
                'status_mentor' => $user->status_mentor,
                'total_poin' => $user->total_poin,
                'onboarding_completed' => $user->onboarding_completed,
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * SCRUM-91: [FR-01][BE] Membuat endpoint login dan autentikasi
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', strtolower(trim($request->email)))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password yang Anda masukkan salah.',
            ], 401);
        }

        // Generate API token
        $token = $user->createToken('mentora_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil!',
            'user' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nama_panggilan' => $user->nama_panggilan,
                'email' => $user->email,
                'universitas' => $user->universitas,
                'prodi' => $user->prodi,
                'semester' => $user->semester,
                'domisili' => $user->domisili,
                'bio' => $user->bio,
                'foto_profil' => $user->foto_profil,
                'role' => $user->role,
                'status_mentor' => $user->status_mentor,
                'total_poin' => $user->total_poin,
                'onboarding_completed' => $user->onboarding_completed,
            ],
            'token' => $token,
        ], 200);
    }

    /**
     * Sesi Pengguna Aktif
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    /**
     * Logout & Revoke Token
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil. Token telah dicabut.',
        ]);
    }
}
