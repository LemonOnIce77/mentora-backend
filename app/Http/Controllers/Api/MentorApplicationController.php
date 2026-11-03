<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorApplication;
use App\Models\MentorCredential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MentorApplicationController extends Controller
{
    /**
     * SCRUM-107: Mengirim formulir pengajuan diri menjadi Mentor
     * POST /api/mentor/apply
     */
    public function apply(Request $request)
    {
        $user = $request->user();

        // Acceptance Criteria: Pengajuan hanya dapat dilakukan bila profil sudah lengkap
        if (!$user->onboarding_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Harap lengkapi profil dan selesaikan onboarding sebelum mengajukan diri menjadi mentor.',
            ], 422);
        }

        // Acceptance Criteria: Cek status jika sudah mentor aktif
        if ($user->status_mentor === 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah terdaftar sebagai Mentor aktif.',
            ], 422);
        }

        // Cek jika sudah memiliki pengajuan pending
        $hasPending = MentorApplication::where('user_id', $user->id)
            ->where('status', 'PENDING')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki pengajuan mentor yang sedang dalam proses review.',
            ], 422);
        }

        $validated = $request->validate([
            'alasan_mengajar' => 'required|string|min:10|max:1000',
            'bidang_keahlian' => 'required|string|max:255',
            'tahun_pengalaman' => 'required|integer|min:0|max:50',
            'tautan_portofolio' => 'nullable|url|max:255',
        ]);

        // Seluruh perubahan (pengajuan, dokumen, status user) disimpan dalam satu transaksi
        $application = DB::transaction(function () use ($user, $validated) {
            // Kunci baris user agar dua request bersamaan tidak sama-sama lolos cek PENDING
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $hasPending = MentorApplication::where('user_id', $lockedUser->id)
                ->where('status', 'PENDING')
                ->exists();

            if ($hasPending) {
                return null;
            }

            $application = MentorApplication::create([
                'user_id' => $lockedUser->id,
                'alasan_mengajar' => $validated['alasan_mengajar'],
                'bidang_keahlian' => $validated['bidang_keahlian'],
                'tahun_pengalaman' => $validated['tahun_pengalaman'],
                'tautan_portofolio' => $validated['tautan_portofolio'] ?? null,
                'status' => 'PENDING',
            ]);

            // Kaitkan dokumen kredensial yang sudah diunggah sebelum pengajuan dikirim
            MentorCredential::where('user_id', $lockedUser->id)
                ->whereNull('mentor_application_id')
                ->update(['mentor_application_id' => $application->id]);

            // Update status_mentor pada user menjadi PENDING
            $lockedUser->update(['status_mentor' => 'PENDING']);

            return $application;
        });

        if ($application === null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki pengajuan mentor yang sedang dalam proses review.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan mentor berhasil dikirim dan sedang menunggu verifikasi admin.',
            'data' => $application,
        ], 201);
    }

    /**
     * SCRUM-108: Melihat status pengajuan mentor saat ini
     * GET /api/mentor/status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        $latestApplication = MentorApplication::where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'status_mentor' => $user->status_mentor,
                'has_application' => $latestApplication !== null,
                'application' => $latestApplication,
            ],
        ]);
    }

    /**
     * SCRUM-108: Melihat daftar seluruh pengajuan mentor (Khusus Admin)
     * GET /api/admin/mentor-applications
     */
    public function indexAdmin(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Endpoint ini hanya untuk Administrator.',
            ], 403);
        }

        $query = MentorApplication::with(['user', 'credentials']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $applications = $query->latest()->get();

        return response()->json([
            'success' => true,
            'total' => $applications->count(),
            'data' => $applications,
        ]);
    }

    /**
     * SCRUM-108: Melakukan verifikasi/review pengajuan mentor (Khusus Admin)
     * POST /api/admin/mentor-applications/{id}/review
     */
    public function reviewAdmin(Request $request, $id)
    {
        $admin = $request->user();
        if ($admin->role !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Administrator yang dapat me-review pengajuan.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:APPROVED,REJECTED',
            'catatan_admin' => 'nullable|string|max:1000',
        ]);

        // Review dilakukan dalam satu transaksi dan hanya boleh sekali (status harus masih PENDING)
        $result = DB::transaction(function () use ($id, $admin, $validated) {
            $application = MentorApplication::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($application->status !== 'PENDING') {
                return ['reviewed' => false, 'application' => $application];
            }

            $application->update([
                'status' => $validated['status'],
                'catatan_admin' => $validated['catatan_admin'] ?? null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Perbarui status_mentor pada pengguna terkait
            $targetUser = User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
            $targetUser->status_mentor = $validated['status'];

            if ($validated['status'] === 'APPROVED') {
                // Tambahkan poin apresiasi mentor baru (10 poin), hanya sekali
                $targetUser->total_poin = ($targetUser->total_poin ?? 0) + 10;
            }

            $targetUser->save();

            return ['reviewed' => true, 'application' => $application];
        });

        if (!$result['reviewed']) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan ini sudah direview sebelumnya (status: ' . $result['application']->status . ').',
            ], 422);
        }

        $application = $result['application'];

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan mentor berhasil diverifikasi menjadi ' . $validated['status'] . '.',
            'data' => $application->fresh(),
        ]);
    }
}
