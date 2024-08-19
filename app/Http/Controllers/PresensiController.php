<?php

namespace App\Http\Controllers;

use App\Models\Dispen;
use App\Models\Image;
use App\Models\Izin;
use App\Models\Kehadiran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PresensiController extends Controller
{

    public function getPresensi(Request $request)
    {
        $siswa_id = Auth::user()->siswa_id;

        // Ambil tanggal dari request, atau gunakan default jika tidak ada
        $date = $request->input('date');

        if ($date) {
            $date = Carbon::parse($date)->startOfDay();
            $data = Kehadiran::where('siswa_id', $siswa_id)
                ->whereDate('tanggal', $date)
                ->get();
            return $this->success($data);
        } else {
            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);

            if ($year && $month) {
                $startDate = Carbon::create($year, $month)->startOfMonth();
                $endDate = Carbon::create($year, $month)->endOfMonth();
            } else if ($year) {
                $startDate = Carbon::create($year)->startOfYear();
                $endDate = Carbon::create($year)->endOfYear();
            } else {
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
            }
            $data = Kehadiran::where('siswa_id', $siswa_id)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->get();

            return $this->success($data);
        }
    }


    public function getIzin()
    {
        $siswa_id = Auth::user()->siswa_id;
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $data = Izin::where('siswa_id', $siswa_id)->where('keterangan', 'izin')->whereBetween('tanggal', [$startDate, $endDate])->get();
        return $this->success($data);
    }

    public function getSakit()
    {
        $siswa_id = Auth::user()->siswa_id;
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $data = Izin::where('siswa_id', $siswa_id)->where('keterangan', 'sakit')->whereBetween('tanggal', [$startDate, $endDate])->get();
        return $this->success($data);
    }

    public function absen()
    {
        $siswa_id = Auth::user()->siswa_id;
        $tanggal = Carbon::now()->format('d-m-Y');
        $currentTime = Carbon::now('Asia/Jakarta');

        // Mengonversi tanggal ke format Y-m-d untuk disimpan ke database
        $tanggalFormatted = Carbon::createFromFormat('d-m-Y', $tanggal)->format('Y-m-d');

        // Cek apakah siswa sudah absen pada hari itu
        $absen = Kehadiran::where('siswa_id', $siswa_id)
            ->where('tanggal', $tanggalFormatted)
            ->first();

        if ($absen) {
            // Jika sudah absen dan ingin absen pulang
            if ($absen->waktu_datang !== null) {
                if ($absen->waktu_pulang) {
                    return response()->json(['message' => 'Anda sudah absen pulang hari ini.'], 422);
                } else {
                    // Cek jika waktu sekarang sudah waktunya pulang
                    $waktu_pulang = Carbon::parse('15:00:00', 'Asia/Jakarta'); // contoh waktu pulang jam 16:00
                    if ($currentTime->greaterThanOrEqualTo($waktu_pulang)) {
                        $absen->update(['waktu_pulang' => $currentTime]);
                        return response()->json(['message' => 'Berhasil Absen Pulang', 'data' => $absen], 201);
                    } else {
                        return response()->json(['message' => 'Belum waktunya pulang.'], 422);
                    }
                }
            } else {
                return response()->json(['message' => 'Anda sudah absen datang hari ini.'], 422);
            }
        } else {
            // Tentukan tenggat waktu untuk absen datang
            $batasWaktu = Carbon::parse('07:00:00', 'Asia/Jakarta');

            // Tentukan keterangan default
            $keterangan = 'hadir';

            // Cek apakah siswa terlambat
            if ($currentTime->greaterThanOrEqualTo($batasWaktu)) {
                $keterangan = 'telat';
            }

            // Jika belum absen, buat data absen baru
            $absen = Kehadiran::create([
                'siswa_id' => $siswa_id,
                'tanggal' => $tanggalFormatted,
                'keterangan' => $keterangan,
                'waktu_datang' => $currentTime,
            ]);

            return response()->json(['message' => 'Berhasil Absen Datang', 'data' => $absen], 200);
        }
    }




    public function reqIzin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'deskripsi' => 'required',
            'keterangan' => 'required|in:sakit,izin',
            'siswa_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'invalid field', 'error' => $validator->errors()], 422);
        }

        $image = $request->file('image');
        $fileName = time() . '_' . $image->getClientOriginalName();
        $filePath = $image->storeAs('uploads', $fileName, 'public');
        $tanggal = Carbon::now();
        $siswa_id = $request->siswa_id;

        $imageUpload = Image::create([
            'file_name' => $fileName,
            'file_path' => '/storage/app/public/' . $filePath
        ]);

        $izin = Izin::create([
            'siswa_id' => $siswa_id,
            'image_id' => $imageUpload->id,
            'tanggal' => $tanggal,
            'keterangan' => $request->keterangan,
            'deskripsi' => $request->deskripsi,
            'status' => 'pending'
        ]);

        $kehadiran = Kehadiran::create([
            'siswa_id' => $siswa_id,
            'tanggal' => $tanggal,
            'keterangan' => $request->keterangan,
            'waktu_datang' => null,
            'waktu_pulang' => null,
        ]);

        return response()->json(['message' => 'Siswa Berhasil Izin', 'data' => $izin], 200);
    }

    public function accIzin($id)
    {
        $approve = Izin::where('id', $id)->where('status', 'pending')->first();
        if (!$approve) {
            $approve->update(['status' => 'approve']);
            return response()->json(['message' => 'Berhasil approve izin siswa' . $approve->siswa->nama], 200);
        } else if ($approve->status === 'approve') {
            return response()->json(['message' => 'izin siswa sudah approve'], 401);
        } else {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }
    }

    public function accDispen($id)
    {
        $approve = Dispen::where('id', $id)->where('status', 'pending')->first();
        if ($approve) {
            $approve->update(['status' => 'approve']);
            return response()->json(['message' => 'Berhasil approve dispen siswa ' . $approve->siswa->nama], 200);
        } else if ($approve->status === 'approve') {
            return response()->json(['message' => 'izin siswa sudah approve'], 401);
        } else {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }
    }

    public function reqDispen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'deskripsi' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'invalid field', 'error' => $validator->errors()], 422);
        }

        $image = $request->file('image');
        $fileName = time() . '_' . $image->getClientOriginalName();
        $filePath = $image->storeAs('uploads', $fileName, 'public');
        $tanggal = Carbon::now();
        $siswa = Auth::user()->siswa_id;

        $imageUpload = Image::create([
            'file_name' => $fileName,
            'file_path' => '/storage/app/public/' . $filePath
        ]);

        $request = Dispen::create([
            'siswa_id' => $siswa,
            'image_id' => $imageUpload->id,
            'deskripsi' => $request->deskripsi,
            'tanggal' => $tanggal,
        ]);

        return response()->json(['message' => 'Berhasil request dispen tunggu approval dari wali kelas'], 200);
    }
}
