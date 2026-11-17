<?php

namespace Tests\Feature;

use App\Models\JadwalKetersediaan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JadwalKetersediaanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'nama_lengkap' => 'Ahmad Nurtajalla',
            'email' => 'ahmad@example.com',
        ]);

        $this->partner = User::factory()->create([
            'nama_lengkap' => 'Sarah Amelia',
            'email' => 'sarah@example.com',
        ]);
    }

    /**
     * Test SCRUM-154: Menambah jadwal ketersediaan baru
     */
    public function test_can_create_schedule(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/schedules', [
            'hari' => 'Senin',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'is_available' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hari', 'Senin');

        $this->assertDatabaseHas('jadwal_ketersediaan', [
            'user_id' => $this->user->id,
            'hari' => 'Senin',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
        ]);
    }

    /**
     * Test SCRUM-154:
     * Acceptance Criteria: Jadwal tidak boleh tumpang tindih dengan jadwal lain milik pengguna yang sama
     */
    public function test_schedule_overlapping_is_rejected(): void
    {
        Sanctum::actingAs($this->user);

        JadwalKetersediaan::create([
            'user_id' => $this->user->id,
            'hari' => 'Senin',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'is_available' => true,
        ]);

        // Coba tambahkan jadwal bertabrakan (10:00 - 12:00) pada hari yang sama
        $conflictResponse = $this->postJson('/api/schedules', [
            'hari' => 'Senin',
            'jam_mulai' => '10:00',
            'jam_selesai' => '12:00',
        ]);

        $conflictResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * Test SCRUM-155:
     * Melihat jadwal ketersediaan aktif milik mitra/mentor
     */
    public function test_can_view_partner_available_schedules(): void
    {
        Sanctum::actingAs($this->user);

        // Partner punya 1 jadwal available dan 1 jadwal sudah terpakai (unavailable)
        JadwalKetersediaan::create([
            'user_id' => $this->partner->id,
            'hari' => 'Selasa',
            'jam_mulai' => '14:00',
            'jam_selesai' => '16:00',
            'is_available' => true,
        ]);

        JadwalKetersediaan::create([
            'user_id' => $this->partner->id,
            'hari' => 'Rabu',
            'jam_mulai' => '10:00',
            'jam_selesai' => '12:00',
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/schedules/user/' . $this->partner->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'schedules')
            ->assertJsonPath('schedules.0.hari', 'Selasa');
    }
}
