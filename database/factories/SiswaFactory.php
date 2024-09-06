<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Siswa>
 */
class SiswaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nisn' => $this->faker->unique()->numberBetween(1000000000, 9999999999), // NISN yang unik
            'nama' => $this->faker->name, // Nama siswa
            'tanggal_lahir' => $this->faker->date('Y-m-d', '2005-12-31'), // Tanggal lahir dalam format Y-m-d
            'jenis_kelamin' => $this->faker->randomElement(['laki-laki', 'perempuan']), // Jenis kelamin
            'noHP' => $this->faker->phoneNumber, // Nomor HP
            'kelas_id' => \App\Models\Kelas::factory(), // Menggunakan factory untuk kelas, sesuaikan jika perlu
        ];
    }
}
