<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\UserSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillController extends Controller
{
    /**
     * SCRUM-102: Menampilkan daftar master skill dengan filter dan pencarian
     * GET /api/skills
     */
    public function index(Request $request)
    {
        $query = Skill::query();

        if ($request->filled('q')) {
          $search = $request->query('q');
            $query->where(function ($q) use ($search) {
                $q->where('nama_skill', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->query('kategori'));
        }

        $skills = $query->orderBy('kategori')->orderBy('nama_skill')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar skill berhasil diambil.',
            'total' => $skills->count(),
            'data' => $skills,
        ]);
    }

    /**
     * SCRUM-102: Menampilkan daftar kategori skill
     * GET /api/skills/categories
     */
    public function categories()
    {
        $categories = Skill::select('kategori', DB::raw('count(*) as total_skills'))
            ->groupBy('kategori')
            ->orderBy('kategori')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar kategori skill berhasil diambil.',
            'data' => $categories,
        ]);
    }

    /**
     * SCRUM-103: Menampilkan daftar skill milik pengguna saat ini
     * GET /api/user/skills
     */
    public function userSkills(Request $request)
    {
        $user = $request->user();

        $teachSkills = $user->userSkills()
            ->where('type', 'teach')
            ->with('skill')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'skill_id' => $item->skill_id,
                    'nama_skill' => $item->skill->nama_skill,
                    'kategori' => $item->skill->kategori,
                    'type' => $item->type,
                    'level' => $item->level,
                    'deskripsi_pengalaman' => $item->deskripsi_pengalaman,
                ];
            });

        $learnSkills = $user->userSkills()
            ->where('type', 'learn')
            ->with('skill')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'skill_id' => $item->skill_id,
                    'nama_skill' => $item->skill->nama_skill,
                    'kategori' => $item->skill->kategori,
                    'type' => $item->type,
                    'level' => $item->level,
                    'deskripsi_pengalaman' => $item->deskripsi_pengalaman,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Skill pengguna berhasil diambil.',
            'data' => [
                'teach' => $teachSkills,
                'learn' => $learnSkills,
                'total_teach' => $teachSkills->count(),
                'total_learn' => $learnSkills->count(),
            ],
        ]);
    }

    /**
     * SCRUM-103: Menambahkan skill ke profil pengguna
     * POST /api/user/skills
     */
    public function storeUserSkill(Request $request)
    {
        $request->validate([
            'skill_id' => 'nullable|exists:skills,id',
            'nama_skill' => 'required_without:skill_id|string|max:100',
            'kategori' => 'nullable|string|max:100',
            'type' => 'required|in:teach,learn',
            'level' => 'required|in:pemula,menengah,mahir',
            'deskripsi_pengalaman' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Resolve or create skill
        if ($request->filled('skill_id')) {
            $skill = Skill::findOrFail($request->skill_id);
        } else {
            $skill = Skill::firstOrCreate(
                ['nama_skill' => trim($request->nama_skill)],
                ['kategori' => $request->kategori ?: 'Lainnya']
            );
        }

        // Check if skill already assigned for this type
        $existing = UserSkill::where('user_id', $user->id)
            ->where('skill_id', $skill->id)
            ->where('type', $request->type)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Skill '{$skill->nama_skill}' sudah terdaftar pada daftar " . ($request->type === 'teach' ? 'keahlian yang diajarkan' : 'minat belajar') . '.',
            ], 422);
        }

        $userSkill = UserSkill::create([
            'user_id' => $user->id,
            'skill_id' => $skill->id,
            'type' => $request->type,
            'level' => $request->level,
            'deskripsi_pengalaman' => $request->deskripsi_pengalaman,
        ]);

        $userSkill->load('skill');

        return response()->json([
            'success' => true,
            'message' => 'Skill berhasil ditambahkan ke profil pengguna.',
            'data' => [
                'id' => $userSkill->id,
                'skill_id' => $userSkill->skill_id,
                'nama_skill' => $userSkill->skill->nama_skill,
                'kategori' => $userSkill->skill->kategori,
                'type' => $userSkill->type,
                'level' => $userSkill->level,
                'deskripsi_pengalaman' => $userSkill->deskripsi_pengalaman,
            ],
        ], 201);
    }

    /**
     * SCRUM-103: Memperbarui data skill pengguna
     * PUT /api/user/skills/{id}
     */
    public function updateUserSkill(Request $request, $id)
    {
        $request->validate([
            'level' => 'sometimes|in:pemula,menengah,mahir',
            'deskripsi_pengalaman' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $userSkill = UserSkill::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $userSkill->update($request->only(['level', 'deskripsi_pengalaman']));
        $userSkill->load('skill');

        return response()->json([
            'success' => true,
            'message' => 'Skill pengguna berhasil diperbarui.',
            'data' => [
                'id' => $userSkill->id,
                'skill_id' => $userSkill->skill_id,
                'nama_skill' => $userSkill->skill->nama_skill,
                'kategori' => $userSkill->skill->kategori,
                'type' => $userSkill->type,
                'level' => $userSkill->level,
                'deskripsi_pengalaman' => $userSkill->deskripsi_pengalaman,
            ],
        ]);
    }

    /**
     * SCRUM-103: Menghapus skill dari profil pengguna
     * DELETE /api/user/skills/{id}
     */
    public function destroyUserSkill(Request $request, $id)
    {
        $user = $request->user();
        $userSkill = UserSkill::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $namaSkill = $userSkill->skill->nama_skill;
        $userSkill->delete();

        return response()->json([
            'success' => true,
            'message' => "Skill '{$namaSkill}' berhasil dihapus dari profil.",
        ]);
    }
}
