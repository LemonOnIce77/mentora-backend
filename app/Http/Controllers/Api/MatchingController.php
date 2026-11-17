<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Http\Request;

class MatchingController extends Controller
{
    protected MatchingService $matchingService;

    public function __construct(MatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * FR-08: SCRUM-135 & SCRUM-136
     * Endpoint rekomendasi hasil skill matching (two-way & one-way match)
     * GET /api/matching
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $filters = [
            'universitas' => $request->query('universitas'),
            'mode_sesi' => $request->query('mode_sesi'),
            'radius' => $request->query('radius'),
            'sort' => $request->query('sort', 'score'),
        ];

        $result = $this->matchingService->findMatches($user, $filters);

        // Jika user belum melengkapi data skill, arahkan untuk melengkapi (Acceptance Criteria FR-08)
        if (!$result['has_skills']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'missing_skills' => true,
                'matches' => [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rekomendasi partner belajar berhasil dimuat.',
            'total' => $result['total'],
            'matches' => $result['matches'],
        ]);
    }

    /**
     * FR-09: SCRUM-141
     * Endpoint pencarian partner belajar dan nearby peer-finder
     * GET /api/peers/search
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $q = trim($request->query('q', ''));
        $radius = $request->query('radius');
        $lat = $request->query('lat', $user->latitude);
        $lng = $request->query('lng', $user->longitude);
        $sort = $request->query('sort', 'score');

        $result = $this->matchingService->findMatches($user, [
            'radius' => $radius,
            'sort' => $sort,
        ]);

        $matches = $result['matches'];

        // Filter text query jika diberikan (nama, universitas, prodi, domisili, atau keahlian)
        if ($q !== '') {
            $qLower = strtolower($q);
            $matches = array_values(array_filter($matches, function ($item) use ($qLower) {
                $searchable = strtolower(implode(' ', [
                    $item['nama_lengkap'] ?? '',
                    $item['nama_panggilan'] ?? '',
                    $item['universitas'] ?? '',
                    $item['prodi'] ?? '',
                    $item['domisili'] ?? '',
                    implode(' ', $item['skills_ditawarkan'] ?? []),
                    implode(' ', $item['skills_dibutuhkan'] ?? []),
                ]));
                return str_contains($searchable, $qLower);
            }));
        }

        return response()->json([
            'success' => true,
            'message' => 'Pencarian partner belajar berhasil.',
            'total' => count($matches),
            'peers' => $matches,
        ]);
    }

    /**
     * FR-09: Nearby Peer-Finder (berdasarkan radius lokasi terdekat)
     * GET /api/peers/nearby
     */
    public function nearby(Request $request)
    {
        $user = $request->user();
        $maxRadius = (float) $request->query('radius', 10); // default 10 km

        if (!is_numeric($user->latitude) || !is_numeric($user->longitude)) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi Anda belum diatur. Harap aktifkan lokasi di profil untuk mencari mitra terdekat.',
                'peers' => [],
            ], 422);
        }

        $result = $this->matchingService->findMatches($user, [
            'radius' => $maxRadius,
            'sort' => 'distance',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mitra belajar di sekitar Anda berhasil dimuat.',
            'radius_km' => $maxRadius,
            'total' => $result['total'],
            'peers' => $result['matches'],
        ]);
    }
}
