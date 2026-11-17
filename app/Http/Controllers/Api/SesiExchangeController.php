<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalKetersediaan;
use App\Models\SesiExchange;
use App\Models\Skill;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SesiExchangeController extends Controller
{
    /**
     * FR-12: SCRUM-159 & SCRUM-160
     * Membuat reservasi sesi belajar baru dengan pengecekan anti-bentrok
     * POST /api/sessions/book
     */
    public function book(Request $request)
    {
        $mentee = $request->user();

        $validated = $request->validate([
            'mentor_id' => 'required|integer|exists:users,id',
            'skill_id' => 'required|integer|exists:skills,id',
            'jadwal_id' => 'nullable|integer|exists:jadwal_ketersediaan,id',
            'tanggal_sesi' => 'required|date|after_or_equal:today',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'metode' => 'required|string|in:online,offline,Online,Offline,Luring,Daring',
            'lokasi' => 'nullable|string|max:255',
        ]);

        $mentorId = (int) $validated['mentor_id'];

        // Tidak boleh memesan sesi dengan diri sendiri
        if ($mentorId === $mentee->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat membuat sesi belajar dengan diri sendiri.',
            ], 422);
        }

        $tanggalSesiStr = Carbon::parse($validated['tanggal_sesi'])->toDateString();
        $jamMulai = $validated['jam_mulai'];
        $jamSelesai = $validated['jam_selesai'];

        // SCRUM-160: Pengecekan Bentrok Jadwal (Anti-Collision Verification)
        // 1. Cek bentrok sesi di pihak Mentor (sesi aktif status BOOKED pada tanggal & jam beririsan)
        $mentorConflict = SesiExchange::where('mentor_id', $mentorId)
            ->where('status', 'BOOKED')
            ->whereDate('tanggal_sesi', $tanggalSesiStr)
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->where('jam_mulai', '<', $jamSelesai)
                    ->where('jam_selesai', '>', $jamMulai);
            })
            ->exists();

        if ($mentorConflict) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => 'Sistem menolak reservasi: Mentor telah memiliki jadwal sesi lain yang bentrok pada waktu tersebut.',
            ], 409);
        }

        // 2. Cek bentrok sesi di pihak Mentee (pemesan)
        $menteeConflict = SesiExchange::where('mentee_id', $mentee->id)
            ->where('status', 'BOOKED')
            ->whereDate('tanggal_sesi', $tanggalSesiStr)
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->where('jam_mulai', '<', $jamSelesai)
                    ->where('jam_selesai', '>', $jamMulai);
            })
            ->exists();

        if ($menteeConflict) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => 'Sistem menolak reservasi: Anda telah memiliki jadwal sesi belajar lain yang bentrok pada waktu tersebut.',
            ], 409);
        }

        // 3. Jika menggunakan jadwal_id, pastikan jadwal masih tersedia dan milik mentor
        if (!empty($validated['jadwal_id'])) {
            $jadwal = JadwalKetersediaan::find($validated['jadwal_id']);
            if (!$jadwal || !$jadwal->is_available || $jadwal->user_id !== $mentorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal ketersediaan yang dipilih sudah tidak tersedia atau telah terpakai.',
                ], 422);
            }
        }

        // 4. Buat reservasi sesi dalam transaksi database atomik
        $session = DB::transaction(function () use ($mentee, $mentorId, $validated, $tanggalSesiStr, $jamMulai, $jamSelesai) {
            // Tandai jadwal sebagai tidak tersedia jika dikaitkan
            if (!empty($validated['jadwal_id'])) {
                JadwalKetersediaan::where('id', $validated['jadwal_id'])
                    ->lockForUpdate()
                    ->update(['is_available' => false]);
            }

            return SesiExchange::create([
                'mentor_id' => $mentorId,
                'mentee_id' => $mentee->id,
                'skill_id' => $validated['skill_id'],
                'jadwal_id' => $validated['jadwal_id'] ?? null,
                'tanggal_sesi' => $tanggalSesiStr,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'metode' => ucfirst(strtolower($validated['metode'])),
                'lokasi' => $validated['lokasi'] ?? ($validated['metode'] === 'online' ? 'Ruang Virtual Mentora' : 'Kampus'),
                'status' => 'BOOKED',
            ]);
        });

        $session->load(['mentor:id,nama_lengkap,nama_panggilan,universitas,foto_profil', 'mentee:id,nama_lengkap,nama_panggilan,universitas', 'skill:id,nama_skill,kategori']);

        return response()->json([
            'success' => true,
            'message' => 'Reservasi sesi belajar berhasil dikonfirmasi tanpa bentrok.',
            'data' => $session,
        ], 201);
    }

    /**
     * FR-12: Melihat daftar seluruh sesi belajar yang diikuti pengguna
     * GET /api/sessions
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SesiExchange::where(function ($q) use ($user) {
            $q->where('mentor_id', $user->id)
                ->orWhere('mentee_id', $user->id);
        })
            ->with([
                'mentor:id,nama_lengkap,nama_panggilan,universitas,foto_profil,status_mentor',
                'mentee:id,nama_lengkap,nama_panggilan,universitas,foto_profil',
                'skill:id,nama_skill,kategori',
            ]);

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        $sessions = $query->orderBy('tanggal_sesi', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => $sessions->count(),
            'data' => $sessions,
        ]);
    }

    /**
     * FR-12: Melihat detail satu sesi belajar
     * GET /api/sessions/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $session = SesiExchange::with([
            'mentor:id,nama_lengkap,nama_panggilan,universitas,prodi,foto_profil,status_mentor',
            'mentee:id,nama_lengkap,nama_panggilan,universitas,prodi,foto_profil',
            'skill:id,nama_skill,kategori',
            'schedule',
        ])->findOrFail($id);

        // Hanya mentor atau mentee yang berhak melihat
        if ($session->mentor_id !== $user->id && $session->mentee_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan partisipan dalam sesi belajar ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    /**
     * FR-12: SCRUM-161
     * Membatalkan sesi belajar sebelum waktu pelaksanaan
     * POST /api/sessions/{id}/cancel
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $session = SesiExchange::findOrFail($id);

        // Otorisasi: hanya mentor atau mentee yang dapat membatalkan
        if ($session->mentor_id !== $user->id && $session->mentee_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya partisipan yang dapat membatalkan sesi.',
            ], 403);
        }

        if ($session->status === 'CANCELLED') {
            return response()->json([
                'success' => false,
                'message' => 'Sesi belajar ini sudah dibatalkan sebelumnya.',
            ], 422);
        }

        if ($session->status === 'COMPLETED') {
            return response()->json([
                'success' => false,
                'message' => 'Sesi belajar yang sudah selesai tidak dapat dibatalkan.',
            ], 422);
        }

        DB::transaction(function () use ($session) {
            $session->update(['status' => 'CANCELLED']);

            // Kembalikan slot jadwal ketersediaan jika sesi dikaitkan ke jadwal
            if ($session->jadwal_id) {
                JadwalKetersediaan::where('id', $session->jadwal_id)->update(['is_available' => true]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Sesi belajar berhasil dibatalkan dan slot jadwal ketersediaan telah dibuka kembali.',
            'data' => $session->fresh(),
        ]);
    }
}
