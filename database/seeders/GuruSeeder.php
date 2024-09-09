<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GuruSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('guru')->insert([
            [
                'nip' => '121201',
                'nama' => 'Anas Fauzi',
                'jabatan' => 'Guru RPL',
                'jenis_kelamin' => 'laki-laki',
                'tanggal_lahir' => '1980-09-12',
                'kelas_id' => 1,
            ],
        ]);
    }
}
