<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * SCRUM-101: Menyiapkan master seed data skill
     */
    public function run(): void
    {
        $skills = [
            // Pemrograman Web
            ['nama_skill' => 'React & Next.js', 'kategori' => 'Pemrograman Web', 'deskripsi' => 'Pengembangan antarmuka modern dengan React dan Next.js'],
            ['nama_skill' => 'Laravel & PHP', 'kategori' => 'Pemrograman Web', 'deskripsi' => 'Backend development dengan framework Laravel dan API Sanctum'],
            ['nama_skill' => 'Tailwind CSS', 'kategori' => 'Pemrograman Web', 'deskripsi' => 'Utility-first styling untuk aplikasi web responsif'],
            ['nama_skill' => 'TypeScript', 'kategori' => 'Pemrograman Web', 'deskripsi' => 'JavaScript dengan static typing untuk kode yang scalable'],
            ['nama_skill' => 'Node.js & Express', 'kategori' => 'Pemrograman Web', 'deskripsi' => 'RESTful API backend development menggunakan JavaScript'],

            // Desain UI/UX
            ['nama_skill' => 'Figma Prototyping', 'kategori' => 'Desain UI/UX', 'deskripsi' => 'Desain wireframe, visual mockup, dan interactive prototype'],
            ['nama_skill' => 'User Research & Testing', 'kategori' => 'Desain UI/UX', 'deskripsi' => 'Metodologi wawancara pengguna dan usability testing'],
            ['nama_skill' => 'Design System', 'kategori' => 'Desain UI/UX', 'deskripsi' => 'Pembuatan komponen UI yang konsisten dan reusable'],

            // Sains Data & AI
            ['nama_skill' => 'Python Data Science', 'kategori' => 'Sains Data & AI', 'deskripsi' => 'Analisis data eksploratif dengan Pandas, NumPy, dan Matplotlib'],
            ['nama_skill' => 'Machine Learning Dasar', 'kategori' => 'Sains Data & AI', 'deskripsi' => 'Supervised dan unsupervised learning dengan Scikit-Learn'],
            ['nama_skill' => 'SQL & Database Design', 'kategori' => 'Sains Data & AI', 'deskripsi' => 'Perancangan query SQL dan pemodelan relasi basis data'],

            // Mobile Development
            ['nama_skill' => 'Flutter & Dart', 'kategori' => 'Mobile Development', 'deskripsi' => 'Pengembangan aplikasi mobile cross-platform Android dan iOS'],
            ['nama_skill' => 'Kotlin Android', 'kategori' => 'Mobile Development', 'deskripsi' => 'Native Android app development dengan modern Kotlin'],

            // Karir & Soft Skills
            ['nama_skill' => 'CV & Portofolio Review', 'kategori' => 'Karir & Soft Skills', 'deskripsi' => 'Optimasi resume ATS dan portofolio proyek magang'],
            ['nama_skill' => 'Scrum & Agile Workflow', 'kategori' => 'Karir & Soft Skills', 'deskripsi' => 'Manajemen proyek perangkat lunak dengan Jira dan Scrum sprint'],
        ];

        foreach ($skills as $skill) {
            Skill::firstOrCreate(
                ['nama_skill' => $skill['nama_skill']],
                $skill
            );
        }
    }
}
