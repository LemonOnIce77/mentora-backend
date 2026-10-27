<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'nama_lengkap' => 'Ahmad Nurtajalla Garda Islam',
            'email' => 'ahmad@telkomuniversity.ac.id',
            'role' => 'Mentee',
            'status_mentor' => 'NONE',
            'onboarding_completed' => true,
        ]);
    }

    /**
     * Test SCRUM-102: Mendapatkan daftar skill dan pencarian
     */
    public function test_can_list_and_search_master_skills(): void
    {
        Skill::create(['nama_skill' => 'React & Next.js', 'kategori' => 'Pemrograman Web']);
        Skill::create(['nama_skill' => 'Laravel & PHP', 'kategori' => 'Pemrograman Web']);
        Skill::create(['nama_skill' => 'Figma UI/UX', 'kategori' => 'Desain UI/UX']);

        // List all
        $response = $this->getJson('/api/skills');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        // Search query
        $searchResponse = $this->getJson('/api/skills?q=React');
        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_skill', 'React & Next.js');

        // Filter category
        $categoryResponse = $this->getJson('/api/skills?kategori=Desain UI/UX');
        $categoryResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_skill', 'Figma UI/UX');
    }

    /**
     * Test SCRUM-102: Mendapatkan daftar kategori skill
     */
    public function test_can_list_skill_categories(): void
    {
        Skill::create(['nama_skill' => 'React', 'kategori' => 'Pemrograman Web']);
        Skill::create(['nama_skill' => 'Laravel', 'kategori' => 'Pemrograman Web']);
        Skill::create(['nama_skill' => 'Figma', 'kategori' => 'Desain UI/UX']);

        $response = $this->getJson('/api/skills/categories');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test SCRUM-103: Menambah skill baru ke profil pengguna
     */
    public function test_can_add_skill_to_user_profile(): void
    {
        $skill = Skill::create(['nama_skill' => 'Python Machine Learning', 'kategori' => 'Sains Data']);

        $payload = [
            'skill_id' => $skill->id,
            'type' => 'teach',
            'level' => 'mahir',
            'deskripsi_pengalaman' => 'Pernah membuat proyek computer vision',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/skills', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama_skill', 'Python Machine Learning')
            ->assertJsonPath('data.type', 'teach')
            ->assertJsonPath('data.level', 'mahir');

        $this->assertDatabaseHas('user_skills', [
            'user_id' => $this->user->id,
            'skill_id' => $skill->id,
            'type' => 'teach',
            'level' => 'mahir',
        ]);
    }

    /**
     * Test SCRUM-103: Mencegah duplikasi skill dengan type yang sama
     */
    public function test_prevents_duplicate_user_skill_for_same_type(): void
    {
        $skill = Skill::create(['nama_skill' => 'Flutter', 'kategori' => 'Mobile']);

        UserSkill::create([
            'user_id' => $this->user->id,
            'skill_id' => $skill->id,
            'type' => 'learn',
            'level' => 'pemula',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/user/skills', [
                'skill_id' => $skill->id,
                'type' => 'learn',
                'level' => 'menengah',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * Test SCRUM-103: Menampilkan daftar skill pengguna yang dikelompokkan
     */
    public function test_can_get_user_skills_grouped_by_type(): void
    {
        $skill1 = Skill::create(['nama_skill' => 'Laravel', 'kategori' => 'Web']);
        $skill2 = Skill::create(['nama_skill' => 'Figma', 'kategori' => 'UI/UX']);

        UserSkill::create([
            'user_id' => $this->user->id,
            'skill_id' => $skill1->id,
            'type' => 'teach',
            'level' => 'mahir',
        ]);

        UserSkill::create([
            'user_id' => $this->user->id,
            'skill_id' => $skill2->id,
            'type' => 'learn',
            'level' => 'pemula',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/user/skills');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_teach', 1)
            ->assertJsonPath('data.total_learn', 1)
            ->assertJsonPath('data.teach.0.nama_skill', 'Laravel')
            ->assertJsonPath('data.learn.0.nama_skill', 'Figma');
    }

    /**
     * Test SCRUM-103: Memperbarui level skill pengguna
     */
    public function test_can_update_user_skill_level(): void
    {
        $skill = Skill::create(['nama_skill' => 'React', 'kategori' => 'Web']);

        $userSkill = UserSkill::create([
            'user_id' => $this->user->id,
            'skill_id' => $skill->id,
            'type' => 'teach',
            'level' => 'pemula',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/user/skills/{$userSkill->id}", [
                'level' => 'mahir',
                'deskripsi_pengalaman' => 'Sudah 2 tahun pengalaman project',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.level', 'mahir');

        $this->assertDatabaseHas('user_skills', [
            'id' => $userSkill->id,
            'level' => 'mahir',
        ]);
    }

    /**
     * Test SCRUM-103: Menghapus skill dari profil pengguna
     */
    public function test_can_delete_user_skill(): void
    {
        $skill = Skill::create(['nama_skill' => 'Vue.js', 'kategori' => 'Web']);

        $userSkill = UserSkill::create([
            'user_id' => $this->user->id,
            'skill_id' => $skill->id,
            'type' => 'learn',
            'level' => 'pemula',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/user/skills/{$userSkill->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_skills', [
            'id' => $userSkill->id,
        ]);
    }
}
