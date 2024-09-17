<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispen extends Model
{
    use HasFactory;
    protected $table = 'dispen';
    protected $fillable = ['siswa_id', 'image_id', 'deskripsi', 'tanggal', 'status', 'keterangan'];
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
