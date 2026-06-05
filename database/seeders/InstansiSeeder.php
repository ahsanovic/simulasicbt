<?php

namespace Database\Seeders;

use App\Models\Instansi;
use Illuminate\Database\Seeder;

class InstansiSeeder extends Seeder
{
    public function run(): void
    {
        $instansis = [
            ['id' => 101, 'nama' => 'Sekretariat Daerah'],
            ['id' => 102, 'nama' => 'Sekretariat DPRD'],
            ['id' => 103, 'nama' => 'Dinas Kesehatan'],
            ['id' => 104, 'nama' => 'Dinas Sosial'],
            ['id' => 105, 'nama' => 'Dinas Pendidikan'],
            ['id' => 106, 'nama' => 'Dinas Perhubungan'],
            ['id' => 107, 'nama' => 'Dinas Komunikasi dan Informatika'],
            ['id' => 108, 'nama' => 'Dinas Tenaga Kerja dan Transmigrasi'],
            ['id' => 109, 'nama' => 'Dinas Kebudayaan dan Pariwisata'],
            ['id' => 110, 'nama' => 'Dinas Koperasi Usaha Kecil dan Menengah'],
            ['id' => 111, 'nama' => 'Dinas Kepemudaan dan Olahraga'],
            ['id' => 112, 'nama' => 'Dinas PU Bina Marga'],
            ['id' => 113, 'nama' => 'Dinas PU Sumber Daya Air'],
            ['id' => 114, 'nama' => 'Dinas Perumahan Rakyat, Kawasan Permukiman dan Cipta Karya'],
            ['id' => 115, 'nama' => 'Dinas Pertanian dan Ketahanan Pangan'],
            ['id' => 116, 'nama' => 'Dinas Perkebunan'],
            ['id' => 117, 'nama' => 'Dinas Peternakan'],
            ['id' => 118, 'nama' => 'Dinas Kelautan dan Perikanan'],
            ['id' => 119, 'nama' => 'Dinas Kehutanan'],
            ['id' => 120, 'nama' => 'Dinas Perindustrian dan Perdagangan'],
            ['id' => 121, 'nama' => 'Dinas Energi dan Sumber Daya Mineral'],
            ['id' => 122, 'nama' => 'Badan Pendapatan Daerah'],
            ['id' => 123, 'nama' => 'Badan Kepegawaian Daerah'],
            ['id' => 124, 'nama' => 'Badan Perencanaan Pembangunan Daerah'],
            ['id' => 125, 'nama' => 'Badan Pengelola Keuangan dan Aset Daerah'],
            ['id' => 126, 'nama' => 'Badan Kesatuan Bangsa dan Politik'],
            ['id' => 127, 'nama' => 'Badan Riset dan Inovasi Daerah'],
            ['id' => 128, 'nama' => 'Badan Pengembangan Sumber Daya Manusia'],
            ['id' => 129, 'nama' => 'Dinas Pemberdayaan Masyarakat dan Desa'],
            ['id' => 130, 'nama' => 'Dinas Lingkungan Hidup'],
            ['id' => 131, 'nama' => 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu'],
            ['id' => 133, 'nama' => 'Dinas Perpustakaan dan Kearsipan'],
            ['id' => 134, 'nama' => 'Dinas Pemberdayaan Perempuan, Perlindungan Anak dan Kependudukan'],
            ['id' => 135, 'nama' => 'Badan Penanggulangan Bencana Daerah'],
            ['id' => 136, 'nama' => 'Inspektorat Provinsi'],
            ['id' => 137, 'nama' => 'Satuan Polisi Pamong Praja'],
            ['id' => 138, 'nama' => 'Badan Koordinasi Wilayah Madiun'],
            ['id' => 139, 'nama' => 'Badan Koordinasi Wilayah Bojonegoro'],
            ['id' => 140, 'nama' => 'Badan Koordinasi Wilayah Malang'],
            ['id' => 141, 'nama' => 'Badan Koordinasi Wilayah Pamekasan'],
            ['id' => 147, 'nama' => 'Badan Penghubung Daerah Provinsi'],
            ['id' => 151, 'nama' => 'Badan Koordinasi Wilayah Jember'],
        ];

        foreach ($instansis as $instansi) {
            Instansi::query()->updateOrCreate(
                ['id' => $instansi['id']],
                ['nama' => $instansi['nama']],
            );
        }
    }
}
