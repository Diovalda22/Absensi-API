<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Izin extends Model
{
    use HasFactory;
    protected $table = 'izin';
    protected $fillable = ['siswa_id', 'image_id', 'keterangan', 'deskripsi', 'tanggal', 'status'];

    protected $with = ['siswa', 'image'];
    public $timestamps = false;

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function image()
    {
        return $this->belongsTo(Image::class, 'image_id');
    }
}
