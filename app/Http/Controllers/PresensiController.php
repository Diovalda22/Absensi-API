<?php

namespace App\Http\Controllers;

use App\Models\Dispen;
use App\Models\Guru;
use App\Models\Image;
use App\Models\Izin;
use App\Models\Kehadiran;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PresensiController extends Controller
{
    public function cekKehadiran()
    {
        $user = Auth::user();
        $guru = Guru::where('id', $user->guru_id)->first();

        if (!$guru) {
            return response()->json(['message' => 'Guru tidak ditemukan'], 404);
        }

        $kelasId = $guru->kelas_id;
        $siswa = Siswa::where('kelas_id', $kelasId)->get();

        if ($siswa->isEmpty()) {
            return response()->json(['message' => 'Tidak ada siswa di kelas ini'], 404);
        }

        $tanggal = Carbon::now()->format('Y-m-d');

        // Ambil data kehadiran berdasarkan siswa di kelas tersebut dan tanggal saat ini
        $kehadiran = Kehadiran::where('tanggal', $tanggal)
            ->whereIn('siswa_id', $siswa->pluck('id'))
            ->get();

        $total_siswa = 36; // Total siswa di kelas

        // Hitung jumlah berdasarkan keterangan
        $hadir = $kehadiran->where('keterangan', 'hadir')->count();
        $telat = $kehadiran->where('keterangan', 'telat')->count();
        $alpha = $kehadiran->where('keterangan', 'alpha')->count();
        $izin = $kehadiran->where('keterangan', 'izin')->count();
        $dispen = $kehadiran->where('keterangan', 'dispen')->count();
        $sakit = $kehadiran->where('keterangan', 'sakit')->count();

        return response()->json([
            'total_siswa' => $total_siswa,
            'hadir' => $hadir,
            'telat' => $telat,
            'alpha' => $alpha,
            'izin' => $izin,
            'dispen' => $dispen,
            'sakit' => $sakit,
            'data' => $kehadiran
        ]);
    }


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


    public function getAbsen()
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Ambil data izin dan dispen berdasarkan rentang tanggal dan status
        $dataIzin = Izin::where('status', 'pending')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        $dataDispen = Dispen::where('status', 'pending')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        // Gabungkan data izin dan dispen
        $dataAbsen = $dataIzin->merge($dataDispen);

        return $this->success($dataAbsen);
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

    public function presensi()
    {
        $siswa_id = Auth::user()->siswa_id;
        $currentTime = Carbon::now('Asia/Jakarta');

        // Format tanggal sekarang untuk keperluan query
        $tanggalFormatted = $currentTime->format('Y-m-d');

        // Cek apakah siswa sudah absen pada hari ini
        $absen = Kehadiran::where('siswa_id', $siswa_id)
            ->where('tanggal', $tanggalFormatted)
            ->first();

        if ($absen) {
            // Jika sudah absen datang, cek apakah siswa sudah absen pulang
            if ($absen->waktu_datang !== null) {
                if ($absen->waktu_pulang) {
                    // Jika sudah absen pulang
                    return response()->json(['message' => 'Anda sudah absen pulang hari ini.'], 422);
                } else {
                    // Tentukan waktu pulang, misalnya pukul 15:00:00
                    $waktu_pulang = Carbon::parse('15:00:00', 'Asia/Jakarta');
                    if ($currentTime->greaterThanOrEqualTo($waktu_pulang)) {
                        // Jika sudah waktunya pulang, update absen dengan waktu_pulang
                        $absen->update(['waktu_pulang' => $currentTime]);
                        return response()->json(['status' => 'pulang', 'message' => 'Berhasil Absen Pulang', 'data' => $absen], 201);
                    } else {
                        // Jika belum waktunya pulang
                        return response()->json(['message' => 'Belum waktunya pulang.'], 422);
                    }
                }
            } else {
                // Jika sudah absen datang, tapi absen pulang belum dilakukan
                return response()->json(['message' => 'Anda sudah absen datang hari ini.'], 422);
            }
        } else {
            // Batas waktu absen datang, misalnya pukul 07:00:00
            $batasWaktu = Carbon::parse('07:00:00', 'Asia/Jakarta');
            $keterangan = 'hadir'; // Default keterangan

            // Cek apakah siswa terlambat
            if ($currentTime->greaterThanOrEqualTo($batasWaktu)) {
                $keterangan = 'telat';
            }

            // Buat data absen baru
            $absen = Kehadiran::create([
                'siswa_id' => $siswa_id,
                'tanggal' => $tanggalFormatted,
                'keterangan' => $keterangan,
                'waktu_datang' => $currentTime,
            ]);

            return response()->json(['status' => 'datang', 'message' => 'Berhasil Absen Datang', 'data' => $absen], 200);
        }
    }

    public function reqIzin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image'      => 'required|image|mimes: jpeg,png,jpg,gif,svg|max: 2048',
            'deskripsi'  => 'required',
            'keterangan' => 'required|in:sakit,izin',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'invalid field', 'error' => $validator->errors()], 422);
        }

        $siswa_id = Auth::user()->siswa_id;
        $tanggal = Carbon::now()->format('Y-m-d');

        $existingIzin = Izin::where('siswa_id', $siswa_id)->whereDate('tanggal', $tanggal)->first();
        if ($existingIzin) {
            return $this->fail('Anda hanya dapat mengajukan izin satu kali per hari', 400);
        }

        $image = $request->file('image');
        $fileName = time() . '_' . $image->getClientOriginalName();
        $filePath = $image->storeAs('uploads', $fileName, 'public');
        $tanggal = Carbon::now();

        $imageUpload = Image::create([
            'file_name' => $fileName,
            'file_path' => '/storage/app/public/' . $filePath
        ]);

        $izin = Izin::create([
            'siswa_id'   => $siswa_id,
            'image_id'   => $imageUpload->id,
            'tanggal'    => $tanggal,
            'keterangan' => $request->keterangan,
            'deskripsi'  => $request->deskripsi,
            'status'     => 'pending'
        ]);

        $kehadiran = Kehadiran::create([
            'siswa_id'     => $siswa_id,
            'tanggal'      => $tanggal,
            'keterangan'   => $request->keterangan,
            'waktu_datang' => null,
            'waktu_pulang' => null,
        ]);

        return response()->json(['message' => 'Siswa Berhasil Izin', 'data' => $izin], 200);
    }

    public function accIzin($id)
    {
        $approve = Izin::where('id', $id)->where('status', 'pending')->first();
        if ($approve) {
            $approve->update(['status' => 'approve']);
            return response()->json(['message' => 'Berhasil approve izin siswa ' . $approve->siswa->nama], 200);
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

        $siswa_id = Auth::user()->siswa_id;
        $tanggal = Carbon::now()->format('Y-m-d');

        $existingDispen = Dispen::where('siswa_id', $siswa_id)->whereDate('tanggal', $tanggal)->first();
        if ($existingDispen) {
            return $this->fail('Anda hanya dapat mengajukan dispen satu kali per hari', 400);
        }

        $image = $request->file('image');
        $fileName = time() . '_' . $image->getClientOriginalName();
        $filePath = $image->storeAs('uploads', $fileName, 'public');

        $imageUpload = Image::create([
            'file_name' => $fileName,
            'file_path' => '/storage/app/public/' . $filePath
        ]);

        $request = Dispen::create([
            'siswa_id' => $siswa_id,
            'image_id' => $imageUpload->id,
            'deskripsi' => $request->deskripsi,
            'tanggal' => $tanggal,
        ]);

        return response()->json(['message' => 'Berhasil request dispen tunggu approval dari wali kelas'], 200);
    }

    public function checkAbsen()
    {
        $siswa_id = Auth::user()->siswa_id;
        $tanggal = Carbon::now()->startOfDay()->format('Y-m-d');
        // var_dump($tanggal);
        $absen = Kehadiran::where('siswa_id', $siswa_id)->whereDate('tanggal', "=", $tanggal)->first();

        // Jika sudah ada absen datang
        if ($absen) {
            // Cek apakah siswa sudah absen pulang
            if ($absen->waktu_pulang !== null || $absen->keterangan === 'izin' || $absen->keterangan === 'dispen') {
                return response()->json(['message' => 'Anda sudah absen pulang hari ini', 'status' => 'pulang'], 201);
            } else {
                return response()->json(['message' => 'Anda sudah absen datang hari ini', 'status' => 'datang'], 200);
            }
        } else {
            return response()->json(['message' => 'Anda belum absen hari ini', 'status' => 'belum'], 202);
        }
    }
}
