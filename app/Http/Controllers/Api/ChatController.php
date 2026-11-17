<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * FR-10: SCRUM-148 & SCRUM-149
     * Melihat riwayat percakapan antara pengguna yang sedang login dan mitra (partner)
     * GET /api/chat/messages?partner_id={id}
     */
    public function getMessages(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'partner_id' => 'required|integer|exists:users,id',
        ]);

        $partnerId = (int) $request->query('partner_id');

        // Pencegahan chat ke diri sendiri
        if ($partnerId === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat membuka ruang percakapan dengan diri sendiri.',
            ], 422);
        }

        // SCRUM-149: Otorisasi ketat - Hanya 2 pengguna yang terlibat yang dapat mengakses percakapan
        $messages = ChatMessage::where(function ($query) use ($user, $partnerId) {
            $query->where('pengirim_id', $user->id)
                ->where('penerima_id', $partnerId);
        })->orWhere(function ($query) use ($user, $partnerId) {
            $query->where('pengirim_id', $partnerId)
                ->where('penerima_id', $user->id);
        })
            ->with(['sender:id,nama_lengkap,nama_panggilan,foto_profil', 'recipient:id,nama_lengkap,nama_panggilan,foto_profil'])
            ->orderBy('waktu_kirim', 'asc')
            ->get();

        // Tandai pesan yang diterima sebagai sudah dibaca (is_read = true)
        ChatMessage::where('pengirim_id', $partnerId)
            ->where('penerima_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $partner = User::select('id', 'nama_lengkap', 'nama_panggilan', 'universitas', 'prodi', 'foto_profil', 'status_mentor')
            ->find($partnerId);

        return response()->json([
            'success' => true,
            'partner' => $partner,
            'total' => $messages->count(),
            'messages' => $messages,
        ]);
    }

    /**
     * FR-10: SCRUM-147
     * Mengirim pesan baru ke mitra belajar
     * POST /api/chat/messages
     */
    public function sendMessage(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'penerima_id' => 'required|integer|exists:users,id',
            'pesan' => 'required|string|max:2000',
            'sesi_id' => 'nullable|integer|exists:sesi_exchanges,id',
        ]);

        $penerimaId = (int) $validated['penerima_id'];

        if ($penerimaId === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengirim pesan ke diri sendiri.',
            ], 422);
        }

        $chat = ChatMessage::create([
            'sesi_id' => $validated['sesi_id'] ?? null,
            'pengirim_id' => $user->id,
            'penerima_id' => $penerimaId,
            'pesan' => trim($validated['pesan']),
            'waktu_kirim' => now(),
            'is_read' => false,
        ]);

        $chat->load(['sender:id,nama_lengkap,nama_panggilan,foto_profil', 'recipient:id,nama_lengkap,nama_panggilan,foto_profil']);

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $chat,
        ], 201);
    }

    /**
     * FR-10: SCRUM-148
     * Melihat daftar ruang percakapan aktif yang melibatkan pengguna
     * GET /api/chat/rooms
     */
    public function getRooms(Request $request)
    {
        $user = $request->user();

        // Ambil ID semua lawan bicara unik
        $partnerIds = ChatMessage::where('pengirim_id', $user->id)
            ->pluck('penerima_id')
            ->merge(
                ChatMessage::where('penerima_id', $user->id)->pluck('pengirim_id')
            )
            ->unique()
            ->values();

        $rooms = [];

        foreach ($partnerIds as $partnerId) {
            $partner = User::select('id', 'nama_lengkap', 'nama_panggilan', 'universitas', 'prodi', 'foto_profil', 'status_mentor')
                ->find($partnerId);

            if (!$partner) {
                continue;
            }

            $lastMessage = ChatMessage::where(function ($q) use ($user, $partnerId) {
                $q->where('pengirim_id', $user->id)->where('penerima_id', $partnerId);
            })->orWhere(function ($q) use ($user, $partnerId) {
                $q->where('pengirim_id', $partnerId)->where('penerima_id', $user->id);
            })
                ->latest('waktu_kirim')
                ->first();

            $unreadCount = ChatMessage::where('pengirim_id', $partnerId)
                ->where('penerima_id', $user->id)
                ->where('is_read', false)
                ->count();

            $rooms[] = [
                'partner' => $partner,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
            ];
        }

        // Urutkan percakapan berdasarkan waktu pesan terakhir
        usort($rooms, function ($a, $b) {
            $timeA = $a['last_message'] ? $a['last_message']->waktu_kirim->timestamp : 0;
            $timeB = $b['last_message'] ? $b['last_message']->waktu_kirim->timestamp : 0;
            return $timeB <=> $timeA;
        });

        return response()->json([
            'success' => true,
            'total' => count($rooms),
            'rooms' => $rooms,
        ]);
    }
}
