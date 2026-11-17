<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeerFinderTest extends TestCase
{
    use RefreshDatabase;

    protected User $userNear;
    protected User $userFar;
    protected User $currentUser;
    protected Skill $skill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skill = Skill::create(['nama_skill' => 'Data Science', 'kategori' => 'AI']);

        // User saat ini di Kampus Telkom Bandung (-6.9740, 107.6303)
        $this->currentUser = User::factory()->create([
            'nama_lengkap' => 'Ahmad Nurtajalla',
            'email' => 'ahmad@example.com',
            'latitude' => -6.9740,
            'longitude' => 107.6303,
            'onboarding_completed' => true,
        ]);

        UserSkill::create([
            'user_id' => $this->currentUser->id,
            'skill_id' => $this->skill->id,
            'type' => 'learn',
            'level' => 'pemula',
        ]);

        // Teman dekat (~1.5 km di Bojongsoang)
        $this->userNear = User::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'universitas' => 'Telkom University',
            'latitude' => -6.9800,
            'longitude' => 107.6350,
            'onboarding_completed' => true,
        ]);

        UserSkill::create([
            'user_id' => $this->userNear->id,
            'skill_id' => $this->skill->id,
            'type' => 'teach',
            'level' => 'mahir',
        ]);

        // Teman jauh (~30 km di Cimahi / Padalarang)
        $this->userFar = User::factory()->create([
            'nama_lengkap' => 'Farhan Pratama',
            'email' => 'farhan@example.com',
            'universitas' => 'Institut Teknologi Bandung',
            'latitude' => -6.8720,
            'longitude' => 107.5750,
            'onboarding_completed' => true,
        ]);

        UserSkill::create([
            'user_id' => $this->userFar->id,
            'skill_id' => $this->skill->id,
            'type' => 'teach',
            'level' => 'menengah',
        ]);
    }

    /**
     * Test SCRUM-140 & SCRUM-141:
     * Menghitung jarak Haversine dan memfilter berdasarkan radius lokasi
     */
    public function test_can_search_peers_and_filter_by_radius(): void
    {
        Sanctum::actingAs($this->currentUser);

        // Cari dengan radius 5 km: hanya Budi (userNear) yang harus muncul
        $response = $this->getJson('/api/peers/search?radius=5');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'peers')
            ->assertJsonPath('peers.0.id', $this->userNear->id);

        $distance = $response->json('peers.0.distance_km');
        $this->assertNotNull($distance);
        $this->assertLessThanOrEqual(5.0, $distance);

        // Cari dengan radius 50 km: kedua user harus muncul
        $responseWide = $this->getJson('/api/peers/search?radius=50');
        $responseWide->assertStatus(200)
            ->assertJsonCount(2, 'peers');
    }

    /**
     * Test SCRUM-141:
     * Pencarian teks bebas (query nama atau universitas)
     */
    public function test_can_search_peers_by_text_query(): void
    {
        Sanctum::actingAs($this->currentUser);

        $response = $this->getJson('/api/peers/search?q=Budi');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'peers')
            ->assertJsonPath('peers.0.nama_lengkap', 'Budi Santoso');
    }
}
