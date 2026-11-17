<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalKetersediaan;
use App\Models\User;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    /**
     * FR-11: SCRUM-154
     * Melihat daftar jadwal ketersediaan milik pengguna yang sedang login
     * GET /api/schedules
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $schedules = JadwalKetersediaan::where('user_id', $user->id)
            ->orderByRaw("CASE 
                WHEN hari = 'Senin' THEN 1
                WHEN hari = 'Selasa' THEN 2
                WHEN hari = 'Rabu' THEN 3
                WHEN hari = 'Kamis' THEN 4
                WHEN hari = 'Jumat' THEN 5
                WHEN hari = 'Sabtu' THEN 6
                WHEN hari = 'Minggu' THEN 7
                ELSE 8 END")
            ->orderBy('jam_mulai', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => $schedules->count(),
            'data' => $schedules,
        ]);
    }

    /**
     * FR-11: SCRUM-154
     * Menambahkan jadwal ketersediaan baru
     * POST /api/schedules
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'hari' => 'required|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'is_available' => 'nullable|boolean',
        ]);

        // Acceptance Criteria: Jadwal tidak boleh tumpang tindih dengan jadwal lain milik pengguna yang sama
        $overlap = JadwalKetersediaan::where('user_id', $user->id)
            ->where('hari', $validated['hari'])
            ->where(function ($query) use ($validated) {
                $query->where('jam_mulai', '<', $validated['jam_selesai'])
                    ->where('jam_selesai', '>', $validated['jam_mulai']);
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ketersediaan tumpang tindih dengan jadwal lain milik Anda pada hari yang sama.',
            ], 422);
        }

        $schedule = JadwalKetersediaan::create([
            'user_id' => $user->id,
            'hari' => $validated['hari'],
            'jam_mulai' => $validated['jam_mulai'],
            'jam_selesai' => $validated['jam_selesai'],
            'is_available' => $validated['is_available'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal ketersediaan berhasil ditambahkan.',
            'data' => $schedule,
        ], 201);
    }

    /**
     * FR-11: SCRUM-154
     * Mengubah jadwal ketersediaan yang ada
     * PUT /api/schedules/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $schedule = JadwalKetersediaan::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'hari' => 'sometimes|required|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam_mulai' => 'sometimes|required|date_format:H:i',
            'jam_selesai' => 'sometimes|required|date_format:H:i',
            'is_available' => 'sometimes|boolean',
        ]);

        $hari = $validated['hari'] ?? $schedule->hari;
        $jamMulai = $validated['jam_mulai'] ?? $schedule->jam_mulai;
        $jamSelesai = $validated['jam_selesai'] ?? $schedule->jam_selesai;

        if ($jamMulai >= $jamSelesai) {
            return response()->json([
                'success' => false,
                'message' => 'Jam selesai harus lebih akhir dari jam mulai.',
            ], 422);
        }

        // Cek overlap terhadap jadwal milik user selain jadwal ini
        $overlap = JadwalKetersediaan::where('user_id', $user->id)
            ->where('id', '!=', $id)
            ->where('hari', $hari)
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->where('jam_mulai', '<', $jamSelesai)
                    ->where('jam_selesai', '>', $jamMulai);
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ketersediaan tumpang tindih dengan jadwal lain milik Anda.',
            ], 422);
        }

        $schedule->update([
            'hari' => $hari,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'is_available' => $validated['is_available'] ?? $schedule->is_available,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal ketersediaan berhasil diperbarui.',
            'data' => $schedule,
        ]);
    }

    /**
     * FR-11: SCRUM-154
     * Menghapus jadwal ketersediaan
     * DELETE /api/schedules/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $schedule = JadwalKetersediaan::where('user_id', $user->id)->findOrFail($id);

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal ketersediaan berhasil dihapus.',
        ]);
    }

    /**
     * FR-11: SCRUM-155
     * Melihat jadwal ketersediaan aktif milik mitra/mentor
     * GET /api/schedules/user/{userId}
     */
    public function viewPartnerSchedules(Request $request, $userId)
    {
        $partner = User::findOrFail($userId);

        // Hanya tampilkan jadwal yang statusnya tersedia (is_available = true)
        $schedules = JadwalKetersediaan::where('user_id', $partner->id)
            ->where('is_available', true)
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return response()->json([
            'success' => true,
            'partner' => [
                'id' => $partner->id,
                'nama_lengkap' => $partner->nama_lengkap,
                'universitas' => $partner->universitas,
                'mode_sesi' => $partner->mode_sesi,
            ],
            'total' => $schedules->count(),
            'schedules' => $schedules,
        ]);
    }
}
