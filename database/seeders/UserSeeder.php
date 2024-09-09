<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'siswa_id' => 1,
                'name' => 'Ferren Diovalda',
                'email' => 'ferrenxx@gmail.com',
                'password' => Hash::make('121212'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'siswa_id' => 2,
                'name' => 'Anggun Mutiara',
                'email' => 'taira123@gmail.com',
                'password' => Hash::make('121212'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'siswa_id' => null,
                'name' => 'Anas Fauzi',
                'email' => 'anas@gmail.com',
                'password' => Hash::make('121212'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
