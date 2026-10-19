<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SCRUM-90 Acceptance Criteria: Pengguna dapat mendaftar dengan data yang valid dan email unik.
     */
    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'nama_lengkap' => 'Ahmad Nurtajalla Garda Islam',
            'email' => 'ahmad@mentora.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registrasi akun berhasil!',
            ])
            ->assertJsonStructure([
                'user' => ['id', 'nama_lengkap', 'email', 'role', 'status_mentor'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'ahmad@mentora.com',
            'nama_lengkap' => 'Ahmad Nurtajalla Garda Islam',
            'role' => 'Mentee',
        ]);

        $user = User::where('email', 'ahmad@mentora.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * SCRUM-90 Acceptance Criteria: Kredensial duplikat ditolak.
     */
    public function test_registration_fails_if_email_is_already_registered(): void
    {
        User::create([
            'nama_lengkap' => 'Existing User',
            'email' => 'sulthoni@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/register', [
            'nama_lengkap' => 'Sulthoni Clone',
            'email' => 'sulthoni@mentora.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * SCRUM-91 Acceptance Criteria: Pengguna dapat login dan diarahkan/diberikan sesi token.
     */
    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Aulia Az Zahra',
            'email' => 'aulia@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'aulia@mentora.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil!',
            ])
            ->assertJsonStructure(['user', 'token']);
    }

    /**
     * SCRUM-91 Acceptance Criteria: Kredensial salah menampilkan pesan kesalahan yang jelas.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'nama_lengkap' => 'Aulia Az Zahra',
            'email' => 'aulia@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'aulia@mentora.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email atau password yang Anda masukkan salah.',
            ]);
    }

    /**
     * Verifikasi Endpoint /api/me dengan Bearer token.
     */
    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Muhammad Yaumil Aditya Warman',
            'email' => 'yaumil@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'yaumil@mentora.com',
                ],
            ]);
    }
}
