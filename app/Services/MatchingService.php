<?php

namespace App\Services;

use App\Models\User;

class MatchingService
{
    /**
     * Menghitung kecocokan dua arah (two-way skill match)
     * FR-08: SCRUM-133 & SCRUM-134
     */
    public function findMatches(User $user, array $filters = []): array
    {
        // Ambil ID skills yang dipelajari dan diajarkan oleh user
        $userLearnSkillIds = $user->learnSkills()->pluck('skills.id')->toArray();
        $userTeachSkillIds = $user->teachSkills()->pluck('skills.id')->toArray();

        // Kasus Tepi SCRUM-136: Pengguna tanpa data skill diarahkan melengkapi skill
        if (empty($userLearnSkillIds) && empty($userTeachSkillIds)) {
            return [
                'has_skills' => false,
                'message' => 'Pengguna tanpa data skill diarahkan melengkapi skill terlebih dahulu.',
                'matches' => [],
            ];
        }

        // Cari kandidat pengguna selain diri sendiri yang sudah aktif
        $candidatesQuery = User::where('id', '!=', $user->id)
            ->with(['teachSkills', 'learnSkills']);

        if (!empty($filters['universitas'])) {
            $candidatesQuery->where('universitas', 'like', '%' . $filters['universitas'] . '%');
        }

        if (!empty($filters['mode_sesi'])) {
            $candidatesQuery->where('mode_sesi', $filters['mode_sesi']);
        }

        $candidates = $candidatesQuery->get();
        $matches = [];

        foreach ($candidates as $candidate) {
            $candidateTeach = $candidate->teachSkills;
            $candidateLearn = $candidate->learnSkills;

            $candidateTeachIds = $candidateTeach->pluck('id')->toArray();
            $candidateLearnIds = $candidateLearn->pluck('id')->toArray();

            // Skill yang diajarkan kandidat dan ingin dipelajari user (A.learn ∩ B.teach)
            $theyTeachMe = array_values(array_intersect($userLearnSkillIds, $candidateTeachIds));
            // Skill yang diajarkan user dan ingin dipelajari kandidat (A.teach ∩ B.learn)
            $iTeachThem = array_values(array_intersect($userTeachSkillIds, $candidateLearnIds));

            $theyTeachMeCount = count($theyTeachMe);
            $iTeachThemCount = count($iTeachThem);

            // Minimal ada satu skill yang beririsan
            if ($theyTeachMeCount === 0 && $iTeachThemCount === 0) {
                continue;
            }

            // Two-way match jika kedua belah pihak saling membutuhkan keahlian masing-masing
            $isTwoWay = ($theyTeachMeCount > 0 && $iTeachThemCount > 0);

            // Algoritma skor kecocokan (FR-08 SCRUM-133)
            if ($isTwoWay) {
                $score = 75 + min(20, ($theyTeachMeCount + $iTeachThemCount) * 5);
            } else {
                $score = 50 + min(25, max($theyTeachMeCount, $iTeachThemCount) * 8);
            }

            // Bonus jika calon mentor terverifikasi
            if ($candidate->status_mentor === 'APPROVED' || $candidate->role === 'Mentor') {
                $score = min(99, $score + 5);
            }

            // Perhitungan jarak Haversine (FR-09 SCRUM-140)
            $distanceKm = null;
            if (
                is_numeric($user->latitude) && is_numeric($user->longitude) &&
                is_numeric($candidate->latitude) && is_numeric($candidate->longitude)
            ) {
                $distanceKm = $this->calculateHaversine(
                    (float) $user->latitude,
                    (float) $user->longitude,
                    (float) $candidate->latitude,
                    (float) $candidate->longitude
                );
            }

            // Filter berdasarkan radius jika diberikan (FR-09 SCRUM-141)
            if (isset($filters['radius']) && $filters['radius'] !== null && $filters['radius'] !== '') {
                $maxRadius = (float) $filters['radius'];
                if ($distanceKm !== null && $distanceKm > $maxRadius) {
                    continue;
                }
            }

            $matches[] = [
                'id' => $candidate->id,
                'nama_lengkap' => $candidate->nama_lengkap,
                'nama_panggilan' => $candidate->nama_panggilan,
                'universitas' => $candidate->universitas,
                'prodi' => $candidate->prodi,
                'semester' => $candidate->semester,
                'domisili' => $candidate->domisili,
                'foto_profil' => $candidate->foto_profil,
                'mode_sesi' => $candidate->mode_sesi ?? 'Fleksibel',
                'status_mentor' => $candidate->status_mentor,
                'is_verified_mentor' => ($candidate->status_mentor === 'APPROVED' || $candidate->role === 'Mentor'),
                'match_score' => $score,
                'is_two_way' => $isTwoWay,
                'skills_ditawarkan' => $candidateTeach->whereIn('id', $theyTeachMe)->pluck('nama_skill')->values()->all(),
                'skills_dibutuhkan' => $candidateLearn->whereIn('id', $iTeachThem)->pluck('nama_skill')->values()->all(),
                'distance_km' => $distanceKm,
                'latitude' => $candidate->latitude,
                'longitude' => $candidate->longitude,
            ];
        }

        // Urutkan berdasarkan sort order (default: skor kecocokan tertinggi)
        $sortBy = $filters['sort'] ?? 'score';
        if ($sortBy === 'distance') {
            usort($matches, function ($a, $b) {
                $distA = $a['distance_km'] ?? PHP_FLOAT_MAX;
                $distB = $b['distance_km'] ?? PHP_FLOAT_MAX;
                return $distA <=> $distB;
            });
        } else {
            usort($matches, function ($a, $b) {
                return $b['match_score'] <=> $a['match_score'];
            });
        }

        return [
            'has_skills' => true,
            'total' => count($matches),
            'matches' => $matches,
        ];
    }

    /**
     * Menghitung jarak lingkaran besar dua titik koordinat GPS (Haversine Formula)
     * FR-09: SCRUM-140
     */
    public function calculateHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // kilometer
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
