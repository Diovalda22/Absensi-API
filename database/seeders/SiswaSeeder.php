<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('siswa')->insert([
            [
                'nisn' => 123456789,
                'nama' => 'Ferren Diovalda',
                'tanggal_lahir' => '2007-02-02',
                'jenis_kelamin' => 'laki-laki',
                'noHP' => '081234567890',
                'kelas_id' => 1, // Pastikan ID kelas ini ada di tabel kelas
            ],
            [
                'nisn' => 987654321,
                'nama' => 'Anggun Mutiara Silvia Itaf Vandana',
                'tanggal_lahir' => '2007-07-11',
                'jenis_kelamin' => 'perempuan',
                'noHP' => '089876543210',
                'kelas_id' => 2, // Pastikan ID kelas ini ada di tabel kelas
            ],
        ]);
    }
}
