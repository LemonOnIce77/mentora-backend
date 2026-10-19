<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * SCRUM-95: [FR-02][BE] Membuat endpoint lihat data profil
     * Acceptance Criteria: Pengguna dapat melihat data profil tersimpan.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Data profil berhasil diambil.',
            'profile' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nama_panggilan' => $user->nama_panggilan,
                'email' => $user->email,
                'universitas' => $user->universitas,
                'prodi' => $user->prodi,
                'semester' => $user->semester,
                'domisili' => $user->domisili,
                'tgl_lahir' => $user->tgl_lahir,
                'bio' => $user->bio,
                'foto_profil' => $user->foto_profil,
                'mode_sesi' => $user->mode_sesi,
                'role' => $user->role,
                'status_mentor' => $user->status_mentor,
                'total_poin' => $user->total_poin,
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'onboarding_completed' => $user->onboarding_completed,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ], 200);
    }

    /**
     * SCRUM-96: [FR-02][BE] Membuat endpoint pembaruan data profil
     * Acceptance Criteria: Pengguna dapat memperbarui data profil (program studi, deskripsi diri, dll).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'universitas' => 'nullable|string|max:255',
            'prodi' => 'nullable|string|max:255',
            'semester' => 'nullable|string|max:50',
            'domisili' => 'nullable|string|max:255',
            'tgl_lahir' => 'nullable|string|max:50',
            'bio' => 'nullable|string|max:1000',
            'foto_profil' => 'nullable|string',
            'mode_sesi' => 'nullable|in:Online,Offline,Fleksibel',
        ], [
            'nama_lengkap.required' => 'Nama lengkap tidak boleh kosong.',
            'mode_sesi.in' => 'Mode sesi harus berupa Online, Offline, atau Fleksibel.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update fields provided in request
        $fields = [
            'nama_lengkap',
            'nama_panggilan',
            'universitas',
            'prodi',
            'semester',
            'domisili',
            'tgl_lahir',
            'bio',
            'foto_profil',
            'mode_sesi',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $user->{$field} = $request->input($field);
            }
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Data profil berhasil diperbarui.',
            'profile' => $user,
        ], 200);
    }

    /**
     * SCRUM-96: [FR-02][BE] Membuat endpoint pembaruan lokasi geolokasi
     * Acceptance Criteria: Pengguna dapat memperbarui lokasi untuk kebutuhan fitur nearby peer-finder.
     */
    public function updateLocation(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'domisili' => 'nullable|string|max:255',
        ], [
            'latitude.required' => 'Titik koordinat latitude wajib dikirimkan.',
            'latitude.numeric' => 'Format latitude harus berupa angka desimal valid.',
            'longitude.required' => 'Titik koordinat longitude wajib dikirimkan.',
            'longitude.numeric' => 'Format longitude harus berupa angka desimal valid.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->latitude = $request->latitude;
        $user->longitude = $request->longitude;

        if ($request->filled('domisili')) {
            $user->domisili = $request->domisili;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Lokasi pengguna berhasil diperbarui untuk fitur pencarian sekitar (nearby peer-finder).',
            'location' => [
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'domisili' => $user->domisili,
            ],
        ], 200);
    }
}
