<?php

namespace Tests\Feature;

use App\Models\MentorApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'nama_lengkap' => 'Ahmad Nurtajalla Garda Islam',
            'email' => 'ahmad@telkomuniversity.ac.id',
            'role' => 'Mentee',
            'status_mentor' => 'NONE',
            'total_poin' => 0,
            'onboarding_completed' => true,
        ]);

        $this->admin = User::factory()->create([
            'nama_lengkap' => 'Admin Mentora',
            'email' => 'admin@mentora.id',
            'role' => 'Admin',
            'status_mentor' => 'APPROVED',
            'onboarding_completed' => true,
        ]);
    }

    /**
     * Test SCRUM-107: Menolak pengajuan bila profil belum lengkap
     */
    public function test_cannot_apply_mentor_if_profile_not_complete(): void
    {
        $incompleteUser = User::factory()->create([
            'onboarding_completed' => false,
            'status_mentor' => 'NONE',
        ]);

        $response = $this->actingAs($incompleteUser, 'sanctum')
            ->postJson('/api/mentor/apply', [
                'alasan_mengajar' => 'Ingin berbagi ilmu pemrograman web',
                'bidang_keahlian' => 'React & Next.js',
                'tahun_pengalaman' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * Test SCRUM-107: Berhasil mengajukan diri menjadi mentor
     */
    public function test_can_submit_mentor_application_successfully(): void
    {
        $payload = [
            'alasan_mengajar' => 'Saya ingin membantu rekan mahasiswa yang kesulitan belajar web development.',
            'bidang_keahlian' => 'Frontend Web Development (React & Tailwind)',
            'tahun_pengalaman' => 2,
            'tautan_portofolio' => 'https://github.com/garda05',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/apply', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PENDING');

        $this->assertDatabaseHas('mentor_applications', [
            'user_id' => $this->user->id,
            'status' => 'PENDING',
            'tahun_pengalaman' => 2,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'status_mentor' => 'PENDING',
        ]);
    }

    /**
     * Test SCRUM-107: Mencegah pengajuan ganda saat masih berstatus PENDING
     */
    public function test_prevents_duplicate_application_when_pending(): void
    {
        MentorApplication::create([
            'user_id' => $this->user->id,
            'alasan_mengajar' => 'Pengajuan pertama saya',
            'bidang_keahlian' => 'UI/UX',
            'tahun_pengalaman' => 1,
            'status' => 'PENDING',
        ]);

        $this->user->update(['status_mentor' => 'PENDING']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/apply', [
                'alasan_mengajar' => 'Pengajuan kedua saya',
                'bidang_keahlian' => 'Backend',
                'tahun_pengalaman' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * Test SCRUM-108: Melihat status pengajuan mentor
     */
    public function test_can_check_mentor_application_status(): void
    {
        MentorApplication::create([
            'user_id' => $this->user->id,
            'alasan_mengajar' => 'Ingin aktif mengajar',
            'bidang_keahlian' => 'Python',
            'tahun_pengalaman' => 1,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/mentor/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_application', true)
            ->assertJsonPath('data.application.status', 'PENDING');
    }

    /**
     * Test SCRUM-108: Admin dapat me-review dan menyetujui pengajuan mentor
     */
    public function test_admin_can_approve_mentor_application(): void
    {
        $application = MentorApplication::create([
            'user_id' => $this->user->id,
            'alasan_mengajar' => 'Ingin aktif mengajar di Mentora',
            'bidang_keahlian' => 'Data Science',
            'tahun_pengalaman' => 2,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mentor-applications/{$application->id}/review", [
                'status' => 'APPROVED',
                'catatan_admin' => 'Selamat, portofolio Anda sangat memadai.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('mentor_applications', [
            'id' => $application->id,
            'status' => 'APPROVED',
            'reviewed_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'status_mentor' => 'APPROVED',
            'total_poin' => 10,
        ]);
    }

    /**
     * Test SCRUM-108: Pengguna non-admin tidak dapat mengakses endpoint review
     */
    public function test_non_admin_cannot_review_applications(): void
    {
        $application = MentorApplication::create([
            'user_id' => $this->user->id,
            'alasan_mengajar' => 'Test pengajuan',
            'bidang_keahlian' => 'Web',
            'tahun_pengalaman' => 1,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/mentor-applications/{$application->id}/review", [
                'status' => 'APPROVED',
            ]);

        $response->assertStatus(403);
    }
}
