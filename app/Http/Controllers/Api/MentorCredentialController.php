<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MentorCredentialController extends Controller
{
    /**
     * SCRUM-113: Mengunggah berkas dokumen kredensial calon mentor
     * POST /api/mentor/credentials
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120', // Maksimal 5MB (5120 KB)
            ],
            'jenis_dokumen' => 'required|in:transkrip,sertifikat,portofolio',
            'judul_dokumen' => 'required|string|max:255',
            'mentor_application_id' => 'nullable|exists:mentor_applications,id',
        ], [
            'file.required' => 'Berkas dokumen wajib diunggah.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas melebihi batas maksimal 5 MB.',
            'jenis_dokumen.required' => 'Jenis dokumen (transkrip, sertifikat, portofolio) wajib dipilih.',
            'jenis_dokumen.in' => 'Jenis dokumen tidak valid.',
            'judul_dokumen.required' => 'Judul dokumen wajib diisi.',
        ]);

        $user = $request->user();
        $file = $request->file('file');

        // Simpan file ke private storage
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $safeFileName = Str::uuid() . '.' . $extension;
        $directory = 'credentials/' . $user->id;

        $filePath = $file->storeAs($directory, $safeFileName, 'local');

        $credential = MentorCredential::create([
            'user_id' => $user->id,
            'mentor_application_id' => $request->mentor_application_id,
            'jenis_dokumen' => $request->jenis_dokumen,
            'judul_dokumen' => $request->judul_dokumen,
            'file_path' => $filePath,
            'file_name' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'status_verifikasi' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen kredensial berhasil diunggah.',
            'data' => [
                'id' => $credential->id,
                'jenis_dokumen' => $credential->jenis_dokumen,
                'judul_dokumen' => $credential->judul_dokumen,
                'file_name' => $credential->file_name,
                'file_size_formatted' => round($credential->file_size / 1024, 2) . ' KB',
                'status_verifikasi' => $credential->status_verifikasi,
                'created_at' => $credential->created_at,
            ],
        ], 201);
    }

    /**
     * SCRUM-114: Melihat daftar dokumen milik pengguna saat ini
     * GET /api/mentor/credentials
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $credentials = MentorCredential::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'jenis_dokumen' => $item->jenis_dokumen,
                    'judul_dokumen' => $item->judul_dokumen,
                    'file_name' => $item->file_name,
                    'file_size_formatted' => round($item->file_size / 1024, 2) . ' KB',
                    'status_verifikasi' => $item->status_verifikasi,
                    'created_at' => $item->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'total' => $credentials->count(),
            'data' => $credentials,
        ]);
    }

    /**
     * SCRUM-114: Mengunduh/mengakses berkas dokumen kredensial
     * GET /api/mentor/credentials/{id}/download
     */
    public function download(Request $request, $id)
    {
        $user = $request->user();
        $credential = MentorCredential::findOrFail($id);

        // Acceptance Criteria: Hanya dapat diakses pemilik dan Admin
        if ($credential->user_id !== $user->id && $user->role !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak berhak mengakses dokumen ini.',
            ], 403);
        }

        if (!Storage::disk('local')->exists($credential->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas fisik dokumen tidak ditemukan di server.',
            ], 404);
        }

        return Storage::disk('local')->download($credential->file_path, $credential->file_name);
    }

    /**
     * SCRUM-114: Menghapus dokumen kredensial
     * DELETE /api/mentor/credentials/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $credential = MentorCredential::findOrFail($id);

        // Hanya pemilik atau admin yang boleh menghapus
        if ($credential->user_id !== $user->id && $user->role !== 'Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak berhak menghapus dokumen ini.',
            ], 403);
        }

        if (Storage::disk('local')->exists($credential->file_path)) {
            Storage::disk('local')->delete($credential->file_path);
        }

        $credential->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen kredensial berhasil dihapus.',
        ]);
    }
}
