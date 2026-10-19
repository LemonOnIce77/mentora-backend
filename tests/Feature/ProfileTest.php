<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SCRUM-95 Acceptance Criteria: Pengguna dapat melihat data profil tersimpan.
     */
    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Ahmad Nurtajalla Garda Islam',
            'nama_panggilan' => 'Garda',
            'email' => 'garda@mentora.com',
            'password' => Hash::make('password123'),
            'universitas' => 'Universitas Telkom',
            'prodi' => 'Sistem Informasi',
            'semester' => '5',
            'domisili' => 'Bandung, Jawa Barat',
            'bio' => 'Tertarik dalam pengembangan software dan backend architecture.',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data profil berhasil diambil.',
                'profile' => [
                    'nama_lengkap' => 'Ahmad Nurtajalla Garda Islam',
                    'nama_panggilan' => 'Garda',
                    'email' => 'garda@mentora.com',
                    'universitas' => 'Universitas Telkom',
                    'prodi' => 'Sistem Informasi',
                    'semester' => '5',
                    'domisili' => 'Bandung, Jawa Barat',
                ],
            ]);
    }

    /**
     * SCRUM-96 Acceptance Criteria: Pengguna dapat memperbarui data profil.
     */
    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Muhammad Yaumil Aditya Warman',
            'email' => 'yaumil@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/profile', [
                'nama_panggilan' => 'Adit',
                'universitas' => 'Universitas Telkom',
                'prodi' => 'Informatika',
                'semester' => '6',
                'domisili' => 'Bandung',
                'bio' => 'Product Owner & Frontend enthusiast.',
                'mode_sesi' => 'Fleksibel',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data profil berhasil diperbarui.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nama_panggilan' => 'Adit',
            'prodi' => 'Informatika',
            'mode_sesi' => 'Fleksibel',
        ]);
    }

    /**
     * SCRUM-96 Acceptance Criteria: Pengguna dapat memperbarui lokasi untuk kebutuhan fitur nearby.
     */
    public function test_authenticated_user_can_update_geolocation(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Aulia Az Zahra',
            'email' => 'aulia@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        // Koordinat Bandung / Telkom University: -6.9740, 107.6303
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/location', [
                'latitude' => -6.9740,
                'longitude' => 107.6303,
                'domisili' => 'Dayeuhkolot, Kabupaten Bandung',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lokasi pengguna berhasil diperbarui untuk fitur pencarian sekitar (nearby peer-finder).',
                'location' => [
                    'latitude' => -6.9740,
                    'longitude' => 107.6303,
                    'domisili' => 'Dayeuhkolot, Kabupaten Bandung',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'latitude' => -6.9740,
            'longitude' => 107.6303,
            'domisili' => 'Dayeuhkolot, Kabupaten Bandung',
        ]);
    }

    /**
     * Test validasi geolokasi invalid.
     */
    public function test_location_update_fails_with_invalid_coordinates(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Tester User',
            'email' => 'test@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/profile/location', [
                'latitude' => 'bukan-angka',
                'longitude' => 107.6303,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /**
     * Test akses tanpa autentikasi ditolak.
     */
    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/profile');
        $response->assertStatus(401);
    }
}
