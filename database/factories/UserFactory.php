<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Password yang dipakai factory (di-hash sekali, dipakai ulang).
     */
    protected static ?string $password;

    /**
     * State default: akun Mentee biasa.
     * Kolom disesuaikan dengan migrasi tabel users Mentora.
     */
    public function definition(): array
    {
        return [
            'nama_lengkap' => fake()->name(),
            'nama_panggilan' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password123'),
            'universitas' => 'Universitas Telkom',
            'prodi' => fake()->randomElement(['Sistem Informasi', 'Informatika', 'Desain Komunikasi Visual']),
            'semester' => (string) fake()->numberBetween(1, 8),
            'domisili' => 'Bandung, Jawa Barat',
            'bio' => fake()->sentence(),
            'mode_sesi' => 'Fleksibel',
            'role' => 'Mentee',
            'status_mentor' => 'NONE',
            'total_poin' => 0,
            'onboarding_completed' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Akun Mentor yang sudah disetujui Admin.
     */
    public function mentor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Mentor',
            'status_mentor' => 'APPROVED',
        ]);
    }

    /**
     * Akun Admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Admin',
            'status_mentor' => 'NONE',
        ]);
    }

    /**
     * Email belum terverifikasi.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
