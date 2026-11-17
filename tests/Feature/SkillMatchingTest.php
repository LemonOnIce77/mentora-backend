<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected Skill $skillWeb;
    protected Skill $skillDesign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skillWeb = Skill::create(['nama_skill' => 'Laravel Backend', 'kategori' => 'Programming']);
        $this->skillDesign = Skill::create(['nama_skill' => 'Figma UI/UX', 'kategori' => 'Design']);

        // User A: Ahli di Laravel (TEACH), ingin belajar Figma (LEARN)
        $this->userA = User::factory()->create([
            'nama_lengkap' => 'Ahmad Nurtajalla',
            'email' => 'ahmad@example.com',
            'latitude' => -6.9740,
            'longitude' => 107.6303,
            'onboarding_completed' => true,
        ]);

        UserSkill::create([
            'user_id' => $this->userA->id,
            'skill_id' => $this->skillWeb->id,
            'type' => 'teach',
            'level' => 'mahir',
        ]);

        UserSkill::create([
            'user_id' => $this->userA->id,
            'skill_id' => $this->skillDesign->id,
            'type' => 'learn',
            'level' => 'pemula',
        ]);

        // User B: Ahli di Figma (TEACH), ingin belajar Laravel (LEARN) -> Perfect Two-Way Match!
        $this->userB = User::factory()->create([
            'nama_lengkap' => 'Sarah Amelia',
            'email' => 'sarah@example.com',
            'latitude' => -6.9750,
            'longitude' => 107.6310,
            'status_mentor' => 'APPROVED',
            'onboarding_completed' => true,
        ]);

        UserSkill::create([
            'user_id' => $this->userB->id,
            'skill_id' => $this->skillDesign->id,
            'type' => 'teach',
            'level' => 'mahir',
        ]);

        UserSkill::create([
            'user_id' => $this->userB->id,
            'skill_id' => $this->skillWeb->id,
            'type' => 'learn',
            'level' => 'pemula',
        ]);
    }

    /**
     * Test SCRUM-133 & SCRUM-134 & SCRUM-135:
     * Algoritma Two-Way Skill Match menghasilkan skor kecocokan tinggi dan data lengkap
     */
    public function test_two_way_skill_matching_returns_high_score(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->getJson('/api/matching');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('matches.0.id', $this->userB->id)
            ->assertJsonPath('matches.0.is_two_way', true)
            ->assertJsonPath('matches.0.is_verified_mentor', true);

        $matchScore = $response->json('matches.0.match_score');
        $this->assertGreaterThanOrEqual(80, $matchScore);
    }

    /**
     * Test SCRUM-136:
     * Kasus tepi: Pengguna tanpa data skill diarahkan untuk melengkapi skill terlebih dahulu
     */
    public function test_user_without_skills_receives_prompt_to_complete_skills(): void
    {
        $userWithoutSkills = User::factory()->create([
            'nama_lengkap' => 'New Student',
            'email' => 'newbie@example.com',
            'onboarding_completed' => false,
        ]);

        Sanctum::actingAs($userWithoutSkills);

        $response = $this->getJson('/api/matching');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('missing_skills', true)
            ->assertJsonPath('message', 'Pengguna tanpa data skill diarahkan melengkapi skill terlebih dahulu.');
    }
}
