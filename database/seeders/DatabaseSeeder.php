<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed 3 akun contoh untuk tes lokal (Mentee, Mentor, Admin).
     * Aman dijalankan berulang kali karena memakai updateOrCreate berdasarkan email.
     *
     * Password semua akun: password123
     */
    public function run(): void
    {
        $akun = [
            [
                'nama_lengkap' => 'Mentee Contoh',
                'nama_panggilan' => 'Mentee',
                'email' => 'mentee@mentora.test',
                'role' => 'Mentee',
                'status_mentor' => 'NONE',
            ],
            [
                'nama_lengkap' => 'Mentor Contoh',
                'nama_panggilan' => 'Mentor',
                'email' => 'mentor@mentora.test',
                'role' => 'Mentor',
                'status_mentor' => 'APPROVED',
            ],
            [
                'nama_lengkap' => 'Admin Mentora',
                'nama_panggilan' => 'Admin',
                'email' => 'admin@mentora.test',
                'role' => 'Admin',
                'status_mentor' => 'NONE',
            ],
        ];

        foreach ($akun as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data + [
                    'password' => Hash::make('password123'),
                    'universitas' => 'Universitas Telkom',
                    'prodi' => 'Sistem Informasi',
                    'semester' => '5',
                    'domisili' => 'Bandung, Jawa Barat',
                    'mode_sesi' => 'Fleksibel',
                    'total_poin' => 0,
                    'onboarding_completed' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
