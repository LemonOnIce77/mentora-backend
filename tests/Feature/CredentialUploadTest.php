<?php

namespace Tests\Feature;

use App\Models\MentorCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CredentialUploadTest extends TestCase
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
            'nama_lengkap' => 'Ahmad Nurtajalla Garda Islam',
            'email' => 'ahmad@telkomuniversity.ac.id',
            'role' => 'Mentee',
            'status_mentor' => 'PENDING',
            'onboarding_completed' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'nama_lengkap' => 'Mahasiswa Lain',
            'email' => 'other@telkomuniversity.ac.id',
            'role' => 'Mentee',
            'status_mentor' => 'NONE',
            'onboarding_completed' => true,
        ]);

        $this->admin = User::factory()->create([
            'nama_lengkap' => 'Admin Kampus',
            'email' => 'admin@mentora.id',
            'role' => 'Admin',
            'status_mentor' => 'APPROVED',
            'onboarding_completed' => true,
        ]);
    }

    /**
     * Test SCRUM-113: Berhasil mengunggah dokumen kredensial valid (PDF/gambar <= 5MB)
     */
    public function test_can_upload_credential_document_successfully(): void
    {
        $file = UploadedFile::fake()->create('transkrip_nilai_ahmad.pdf', 1500, 'application/pdf');

        $payload = [
            'file' => $file,
            'jenis_dokumen' => 'transkrip',
            'judul_dokumen' => 'Transkrip Akademik Semester 4',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/credentials', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.jenis_dokumen', 'transkrip')
            ->assertJsonPath('data.judul_dokumen', 'Transkrip Akademik Semester 4');

        $this->assertDatabaseHas('mentor_credentials', [
            'user_id' => $this->user->id,
            'jenis_dokumen' => 'transkrip',
            'judul_dokumen' => 'Transkrip Akademik Semester 4',
            'file_name' => 'transkrip_nilai_ahmad.pdf',
        ]);

        $credential = MentorCredential::first();
        Storage::disk('local')->assertExists($credential->file_path);
    }

    /**
     * Test SCRUM-113: Menolak format berkas yang tidak didukung
     */
    public function test_rejects_unsupported_file_format(): void
    {
        $file = UploadedFile::fake()->create('script_ilegal.exe', 500, 'application/x-msdownload');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/credentials', [
                'file' => $file,
                'jenis_dokumen' => 'sertifikat',
                'judul_dokumen' => 'Berkas Tidak Valid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test SCRUM-113: Menolak berkas yang melebihi batas 5MB
     */
    public function test_rejects_file_exceeding_max_size(): void
    {
        // 6MB = 6144 KB
        $oversizedFile = UploadedFile::fake()->create('portofolio_raksasa.pdf', 6144, 'application/pdf');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mentor/credentials', [
                'file' => $oversizedFile,
                'jenis_dokumen' => 'portofolio',
                'judul_dokumen' => 'Portofolio Terlalu Besar',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test SCRUM-114: Melihat daftar dokumen milik sendiri
     */
    public function test_can_list_user_credentials(): void
    {
        MentorCredential::create([
            'user_id' => $this->user->id,
            'jenis_dokumen' => 'sertifikat',
            'judul_dokumen' => 'Sertifikat React Dicoding',
            'file_path' => 'credentials/1/test1.pdf',
            'file_name' => 'sertifikat_react.pdf',
            'file_size' => 102400,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/mentor/credentials');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.judul_dokumen', 'Sertifikat React Dicoding');
    }

    /**
     * Test SCRUM-114: Pemilik dokumen dapat mengunduh berkas
     */
    public function test_owner_can_download_document(): void
    {
        $filePath = 'credentials/' . $this->user->id . '/test_doc.pdf';
        Storage::disk('local')->put($filePath, 'konten rahasia dokumen kredensial');

        $credential = MentorCredential::create([
            'user_id' => $this->user->id,
            'jenis_dokumen' => 'transkrip',
            'judul_dokumen' => 'Transkrip Nilai',
            'file_path' => $filePath,
            'file_name' => 'transkrip.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/mentor/credentials/{$credential->id}/download");

        $response->assertStatus(200);
    }

    /**
     * Test SCRUM-114: Admin dapat mengunduh dokumen kredensial mahasiswa untuk verifikasi
     */
    public function test_admin_can_download_any_credential_document(): void
    {
        $filePath = 'credentials/' . $this->user->id . '/test_doc.pdf';
        Storage::disk('local')->put($filePath, 'konten rahasia dokumen kredensial');

        $credential = MentorCredential::create([
            'user_id' => $this->user->id,
            'jenis_dokumen' => 'transkrip',
            'judul_dokumen' => 'Transkrip Nilai Mahasiswa',
            'file_path' => $filePath,
            'file_name' => 'transkrip.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/mentor/credentials/{$credential->id}/download");

        $response->assertStatus(200);
    }

    /**
     * Test SCRUM-114: Pengguna lain dilarang mengakses dokumen (403 Forbidden)
     */
    public function test_unauthorized_user_cannot_access_or_download_document(): void
    {
        $filePath = 'credentials/' . $this->user->id . '/test_doc.pdf';
        Storage::disk('local')->put($filePath, 'konten dokumen milik Ahmad');

        $credential = MentorCredential::create([
            'user_id' => $this->user->id,
            'jenis_dokumen' => 'transkrip',
            'judul_dokumen' => 'Transkrip Nilai Ahmad',
            'file_path' => $filePath,
            'file_name' => 'transkrip_ahmad.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->get("/api/mentor/credentials/{$credential->id}/download");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * Test SCRUM-114: Menghapus dokumen kredensial
     */
    public function test_owner_can_delete_credential_document(): void
    {
        $filePath = 'credentials/' . $this->user->id . '/test_delete.pdf';
        Storage::disk('local')->put($filePath, 'konten siap hapus');

        $credential = MentorCredential::create([
            'user_id' => $this->user->id,
            'jenis_dokumen' => 'portofolio',
            'judul_dokumen' => 'Portofolio Lama',
            'file_path' => $filePath,
            'file_name' => 'portofolio_lama.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/mentor/credentials/{$credential->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('mentor_credentials', [
            'id' => $credential->id,
        ]);

        Storage::disk('local')->assertMissing($filePath);
    }
}
