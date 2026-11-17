<?php

namespace Tests\Feature;

use App\Models\JadwalKetersediaan;
use App\Models\SesiExchange;
use App\Models\Skill;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SesiExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $mentor;
    protected User $mentee;
    protected Skill $skill;
    protected JadwalKetersediaan $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skill = Skill::create(['nama_skill' => 'Next.js & React', 'kategori' => 'Programming']);

        $this->mentor = User::factory()->create([
            'nama_lengkap' => 'Sarah Amelia',
            'email' => 'sarah@example.com',
            'status_mentor' => 'APPROVED',
        ]);

        $this->mentee = User::factory()->create([
            'nama_lengkap' => 'Ahmad Nurtajalla',
            'email' => 'ahmad@example.com',
        ]);

        $this->jadwal = JadwalKetersediaan::create([
            'user_id' => $this->mentor->id,
            'hari' => 'Kamis',
            'jam_mulai' => '13:00',
            'jam_selesai' => '15:00',
            'is_available' => true,
        ]);
    }

    /**
     * Test SCRUM-159: Reservasi tersimpan pada Sesi_Exchange setelah jadwal dikonfirmasi
     * dan jadwal ditandai tidak tersedia
     */
    public function test_can_book_session_successfully(): void
    {
        Sanctum::actingAs($this->mentee);

        $tanggal = Carbon::tomorrow()->toDateString();

        $response = $this->postJson('/api/sessions/book', [
            'mentor_id' => $this->mentor->id,
            'skill_id' => $this->skill->id,
            'jadwal_id' => $this->jadwal->id,
            'tanggal_sesi' => $tanggal,
            'jam_mulai' => '13:00',
            'jam_selesai' => '15:00',
            'metode' => 'online',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'BOOKED')
            ->assertJsonPath('data.mentor_id', $this->mentor->id)
            ->assertJsonPath('data.mentee_id', $this->mentee->id);

        $this->assertDatabaseHas('sesi_exchanges', [
            'mentor_id' => $this->mentor->id,
            'mentee_id' => $this->mentee->id,
            'status' => 'BOOKED',
        ]);

        // Jadwal ditandai is_available = false (Acceptance criteria: satu jadwal hanya untuk 1 sesi)
        $this->assertFalse($this->jadwal->fresh()->is_available);
    }

    /**
     * Test SCRUM-160:
     * Sistem menolak reservasi yang bentrok dengan sesi lain milik salah satu pengguna
     */
    public function test_system_rejects_colliding_session_reservation(): void
    {
        Sanctum::actingAs($this->mentee);

        $tanggal = Carbon::tomorrow()->toDateString();

        // Buat sesi pertama (13:00 - 15:00)
        SesiExchange::create([
            'mentor_id' => $this->mentor->id,
            'mentee_id' => $this->mentee->id,
            'skill_id' => $this->skill->id,
            'tanggal_sesi' => $tanggal,
            'jam_mulai' => '13:00',
            'jam_selesai' => '15:00',
            'metode' => 'Online',
            'status' => 'BOOKED',
        ]);

        // Coba pesan sesi yang beririsan (14:00 - 16:00) pada hari yang sama
        $response = $this->postJson('/api/sessions/book', [
            'mentor_id' => $this->mentor->id,
            'skill_id' => $this->skill->id,
            'tanggal_sesi' => $tanggal,
            'jam_mulai' => '14:00',
            'jam_selesai' => '16:00',
            'metode' => 'online',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('conflict', true);
    }

    /**
     * Test SCRUM-161:
     * Pengguna dapat membatalkan sesi sebelum waktu pelaksanaan dan slot jadwal dibuka kembali
     */
    public function test_user_can_cancel_booked_session(): void
    {
        Sanctum::actingAs($this->mentee);

        $tanggal = Carbon::tomorrow()->toDateString();

        $session = SesiExchange::create([
            'mentor_id' => $this->mentor->id,
            'mentee_id' => $this->mentee->id,
            'skill_id' => $this->skill->id,
            'jadwal_id' => $this->jadwal->id,
            'tanggal_sesi' => $tanggal,
            'jam_mulai' => '13:00',
            'jam_selesai' => '15:00',
            'metode' => 'Online',
            'status' => 'BOOKED',
        ]);

        $this->jadwal->update(['is_available' => false]);

        $cancelResponse = $this->postJson('/api/sessions/' . $session->id . '/cancel');

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertEquals('CANCELLED', $session->fresh()->status);
        // Jadwal dibuka kembali
        $this->assertTrue($this->jadwal->fresh()->is_available);
    }
}
