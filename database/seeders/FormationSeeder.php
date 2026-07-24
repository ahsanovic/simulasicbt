<?php

namespace Database\Seeders;

use App\Models\Formation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FormationSeeder extends Seeder
{
    public function run(): void
    {
        $formations = [
            // --- 1. RUMPUN TEKNOLOGI INFORMASI & KOMUNIKASI ---
            ['name' => 'Pranata Komputer', 'group' => 'Teknologi Informasi'],
            ['name' => 'Manggala Informatika', 'group' => 'Teknologi Informasi'],
            ['name' => 'Analis Sistem Informasi', 'group' => 'Teknologi Informasi'],
            ['name' => 'Pengembang Teknologi Pembelajaran', 'group' => 'Teknologi Informasi'],
            ['name' => 'Analis Tata Kelola Teknologi Informasi', 'group' => 'Teknologi Informasi'],
            ['name' => 'Pranata Humas (Hubungan Masyarakat)', 'group' => 'Teknologi Informasi'],

            // --- 2. RUMPUN KEUANGAN, AKUNTANSI & PENGAWASAN ---
            ['name' => 'Auditor', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Analis Keuangan Pusat dan Daerah', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Pengelola Pengadaan Barang/Jasa', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Penilai Pemerintah', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Pemeriksa Pajak', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Pemeriksa Bea dan Cukai', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Analis Anggaran', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Analis Perbendaharaan Negara', 'group' => 'Keuangan & Pengawasan'],
            ['name' => 'Penata Kadastral', 'group' => 'Keuangan & Pengawasan'],

            // --- 3. RUMPUN HUKUM, HAK ASASI & TATA KELOLA ---
            ['name' => 'Analis Kebijakan', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Analis Hukum', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Perancang Peraturan Perundang-undangan', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Analis Pelanggaran Hak Asasi Manusia', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Analis Keimigrasian', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Pemeriksa Keimigrasian', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Penyuluh Hukum', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Analis Pemasyarakatan', 'group' => 'Hukum & Tata Kelola'],
            ['name' => 'Petugas Pemasyarakatan (Polsuspas)', 'group' => 'Hukum & Tata Kelola'],

            // --- 4. RUMPUN MANAJEMEN, SDM & ADMINISTRASI ---
            ['name' => 'Analis Sumber Daya Manusia Aparatur (SDMA)', 'group' => 'Manajemen & Administrasi'],
            ['name' => 'Asesor Sumber Daya Manusia Aparatur', 'group' => 'Manajemen & Administrasi'],
            ['name' => 'Perencana', 'group' => 'Manajemen & Administrasi'],
            ['name' => 'Pranata Kearsipan (Arsiparis)', 'group' => 'Manajemen & Administrasi'],
            ['name' => 'Analis Organisasi dan Tatalaksana', 'group' => 'Manajemen & Administrasi'],
            ['name' => 'Penata Kelola Organisasi', 'group' => 'Manajemen & Administrasi'],
            ['name' => 'Pengelola Administrasi Pemerintahan', 'group' => 'Manajemen & Administrasi'],
            ['name' => 'Penata Layanan Operasional', 'group' => 'Manajemen & Administrasi'],

            // --- 5. RUMPUN KESEHATAN ---
            ['name' => 'Dokter', 'group' => 'Kesehatan'],
            ['name' => 'Dokter Gigi', 'group' => 'Kesehatan'],
            ['name' => 'Perawat', 'group' => 'Kesehatan'],
            ['name' => 'Bidan', 'group' => 'Kesehatan'],
            ['name' => 'Apoteker', 'group' => 'Kesehatan'],
            ['name' => 'Asisten Apoteker', 'group' => 'Kesehatan'],
            ['name' => 'Epidemiolog Kesehatan', 'group' => 'Kesehatan'],
            ['name' => 'Tenaga Sanitasi Lingkungan', 'group' => 'Kesehatan'],
            ['name' => 'Nutrisionis', 'group' => 'Kesehatan'],
            ['name' => 'Pranata Laboratorium Kesehatan', 'group' => 'Kesehatan'],
            ['name' => 'Physioterapis', 'group' => 'Kesehatan'],
            ['name' => 'Penyuluh Kesehatan Masyarakat', 'group' => 'Kesehatan'],

            // --- 6. RUMPUN PENDIDIKAN & KEBUDAYAAN ---
            ['name' => 'Guru Kelas', 'group' => 'Pendidikan'],
            ['name' => 'Guru Bimbingan Konseling', 'group' => 'Pendidikan'],
            ['name' => 'Guru Agama', 'group' => 'Pendidikan'],
            ['name' => 'Guru Bahasa Indonesia', 'group' => 'Pendidikan'],
            ['name' => 'Guru Bahasa Inggris', 'group' => 'Pendidikan'],
            ['name' => 'Guru Matematika', 'group' => 'Pendidikan'],
            ['name' => 'Guru IPA / IPS', 'group' => 'Pendidikan'],
            ['name' => 'Dosen', 'group' => 'Pendidikan'],
            ['name' => 'Pamong Belajar', 'group' => 'Pendidikan'],
            ['name' => 'Pustakawan', 'group' => 'Pendidikan'],
            ['name' => 'Pamong Budaya', 'group' => 'Pendidikan'],

            // --- 7. RUMPUN TEKNIK, PEKERJAAN UMUM & LINGKUNGAN ---
            ['name' => 'Teknik Jalan dan Jembatan', 'group' => 'Teknik & Lingkungan'],
            ['name' => 'Teknik Pengairan', 'group' => 'Teknik & Lingkungan'],
            ['name' => 'Teknik Tata Bangunan dan Perumahan', 'group' => 'Teknik & Lingkungan'],
            ['name' => 'Penata Ruang', 'group' => 'Teknik & Lingkungan'],
            ['name' => 'Pengendali Dampak Lingkungan', 'group' => 'Teknik & Lingkungan'],
            ['name' => 'Pengawas Lingkungan Hidup', 'group' => 'Teknik & Lingkungan'],
            ['name' => 'Analis Kebencanaan / Penanggulangan Bencana', 'group' => 'Teknik & Lingkungan'],
            ['name' => 'Surveyor Pemetaan', 'group' => 'Teknik & Lingkungan'],

            // --- 8. RUMPUN PERTANIAN, PERIKANAN & PETERNAKAN ---
            ['name' => 'Penyuluh Pertanian', 'group' => 'Pertanian & Pangan'],
            ['name' => 'Pengawas Benih Tanaman', 'group' => 'Pertanian & Pangan'],
            ['name' => 'Penyuluh Perikanan', 'group' => 'Pertanian & Pangan'],
            ['name' => 'Pengawas Perikanan', 'group' => 'Pertanian & Pangan'],
            ['name' => 'Medik Veteriner (Dokter Hewan)', 'group' => 'Pertanian & Pangan'],
            ['name' => 'Paramedik Veteriner', 'group' => 'Pertanian & Pangan'],
            ['name' => 'Pengendali Hama dan Penyakit Tumbuhan', 'group' => 'Pertanian & Pangan'],

            // --- 9. RUMPUN PERHUBUNGAN, PERDAGANGAN & SOSIAL ---
            ['name' => 'Penguji Kendaraan Bermotor', 'group' => 'Perhubungan & Sosial'],
            ['name' => 'Analis Transportasi', 'group' => 'Perhubungan & Sosial'],
            ['name' => 'Penyuluh Perindustrian dan Perdagangan', 'group' => 'Perhubungan & Sosial'],
            ['name' => 'Penerjemah', 'group' => 'Perhubungan & Sosial'],
            ['name' => 'Pekerja Sosial', 'group' => 'Perhubungan & Sosial'],
            ['name' => 'Penyuluh Sosial', 'group' => 'Perhubungan & Sosial'],
            ['name' => 'Satuan Polisi Pamong Praja (Satpol PP)', 'group' => 'Perhubungan & Sosial'],

            // --- 10. CATEGORY CATCH-ALL (UNTUK PENAMPUNG) ---
            ['name' => 'Jabatan Pelaksana / Administrasi Umum', 'group' => 'Umum'],
            ['name' => 'Penata Kelola Pemerintahan (Umum)', 'group' => 'Umum'],
        ];

        foreach ($formations as $f) {
            Formation::updateOrCreate(
                ['name' => $f['name']],
                [
                    'group' => $f['group'],
                    'slug'  => Str::slug($f['name']),
                ]
            );
        }
    }
}