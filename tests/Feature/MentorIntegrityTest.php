<?php

namespace Tests\Feature;

use App\Models\MentorApplication;
use App\Models\MentorCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tes integritas data FR-04 (pengajuan mentor) dan FR-05 (dokumen kredensial).
 */
class MentorIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create([
            'role' => 'Mentee',
            'status_mentor' => 'NONE',
            'total_poin' => 0,
            'onboarding_completed' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'role' => 'Mentee',
            'status_mentor' => 'NONE',
            'onboarding_completed' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'Admin',
            'status_mentor' => 'NONE',
            'onboarding_completed' => true,
        ]);
    }

    private function pendingApplicationFor(User $user): MentorApplication
    {
        return MentorApplication::create([
            'user_id' => $user->id,
            'alasan_mengajar' => 'Ingin aktif mengajar di Mentora',
            'bidang_keahlian' => 'Data Science',
            'tahun_pengalaman' => 2,
            'status' => 'PENDING',
        ]);
    }

    private function applyPayload(): array
    {
        return [
            'alasan_mengajar' => 'Saya ingin membantu rekan mahasiswa belajar pemrograman.',
            'bidang_keahlian' => 'Laravel & PHP',
            'tahun_pengalaman' => 2,
        ];
    }

    /**
     * Review yang sama tidak boleh diproses dua kali dan poin hanya bertambah sekali.
     */
    public function test_review_cannot_be_applied_twice_and_points_added_once(): void
    {
        $application = $this->pendingApplicationFor($this->user);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mentor-applications/{$application->id}/review", ['status' => 'APPROVED'])
            ->assertStatus(200);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mentor-applications/{$application->id}/review", ['status' => 'APPROVED'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'status_mentor' => 'APPROVED',
            'total_poin' => 10,
        ]);
    }

    /**
     * Pengajuan yang sudah ditolak tidak dapat diubah menjadi disetujui lewat review kedua.
     */
    public function test_rejected_application_cannot_be_flipped_to_approved(): void
    {
        $application = $this->pendingApplicationFor($this->user);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mentor-applications/{$application->id}/review", ['status' => 'REJECTED'])
            ->assertStatus(200);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mentor-applications/{$application->id}/review", ['status' => 'APPROVED'])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'status_mentor' => 'REJECTED',
            'total_poin' => 0,
        ]);
    }

    /**
     * Dokumen yang diunggah sebelum mengajukan akan dikaitkan ke pengajuan baru.
     */
    public function test_credentials_uploaded_before_apply_are_linked_to_application(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/credentials', [
                'file' => UploadedFile::fake()->create('transkrip.pdf', 500, 'application/pdf'),
                'jenis_dokumen' => 'transkrip',
                'judul_dokumen' => 'Transkrip Semester 5',
            ])
            ->assertStatus(201);

        $this->assertNull(MentorCredential::first()->mentor_application_id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/apply', $this->applyPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('mentor_credentials', [
            'user_id' => $this->user->id,
            'mentor_application_id' => $response->json('data.id'),
        ]);
    }

    /**
     * Dokumen yang diunggah setelah mengajukan otomatis terkait pengajuan PENDING.
     */
    public function test_credential_uploaded_after_apply_is_linked_to_pending_application(): void
    {
        $application = $this->pendingApplicationFor($this->user);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/credentials', [
                'file' => UploadedFile::fake()->create('sertifikat.pdf', 300, 'application/pdf'),
                'jenis_dokumen' => 'sertifikat',
                'judul_dokumen' => 'Sertifikat Laravel',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('mentor_credentials', [
            'user_id' => $this->user->id,
            'mentor_application_id' => $application->id,
        ]);
    }

    /**
     * Dokumen tidak boleh dikaitkan ke pengajuan milik pengguna lain.
     */
    public function test_cannot_attach_credential_to_another_users_application(): void
    {
        $foreignApplication = $this->pendingApplicationFor($this->otherUser);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/credentials', [
                'file' => UploadedFile::fake()->create('transkrip.pdf', 500, 'application/pdf'),
                'jenis_dokumen' => 'transkrip',
                'judul_dokumen' => 'Transkrip Curang',
                'mentor_application_id' => $foreignApplication->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('mentor_credentials', 0);
    }

    /**
     * Pemilik tidak boleh menghapus dokumen yang terkait pengajuan PENDING (bukti verifikasi).
     */
    public function test_owner_cannot_delete_credential_of_pending_application(): void
    {
        $application = $this->pendingApplicationFor($this->user);

        $filePath = 'credentials/' . $this->user->id . '/bukti.pdf';
        Storage::disk('local')->put($filePath, 'isi dokumen');

        $credential = MentorCredential::create([
            'user_id' => $this->user->id,
            'mentor_application_id' => $application->id,
            'jenis_dokumen' => 'transkrip',
            'judul_dokumen' => 'Transkrip',
            'file_path' => $filePath,
            'file_name' => 'bukti.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/mentor/credentials/{$credential->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('mentor_credentials', ['id' => $credential->id]);
               /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists($filePath);
    }
}
