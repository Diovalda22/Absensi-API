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

        $tanggal = Carbon::now()->timezone('Asia/Jakarta')->format('Y-m-d');
        $waktuSekarang = Carbon::now();

        // Ambil data kehadiran berdasarkan siswa di kelas tersebut dan tanggal saat ini
        $kehadiran = Kehadiran::where('tanggal', $tanggal)
            ->whereIn('siswa_id', $siswa->pluck('id'))
            ->get();

        $total_siswa = $siswa->count();

        // Cek jika waktu sekarang melewati jam 15:00
        if ($waktuSekarang->hour >= 15) {
            // Cari siswa yang belum memiliki catatan kehadiran hari ini dan tandai sebagai alpha
            $siswaTanpaKehadiran = $siswa->whereNotIn('id', $kehadiran->pluck('siswa_id'));

            foreach ($siswaTanpaKehadiran as $siswa) {
                Kehadiran::create([
                    'siswa_id' => $siswa->id,
                    'tanggal' => $tanggal,
                    'keterangan' => 'alpha',
                    'waktu_datang' => null,
                    'waktu_pulang' => null,
                ]);
            }

            // Refresh data kehadiran setelah memasukkan siswa alpha
            $kehadiran = Kehadiran::where('tanggal', $tanggal)
                ->whereIn('siswa_id', $siswa->pluck('id'))
                ->get();
        }

        // Update kehadiran menjadi alpha jika waktu_datang dan waktu_pulang null, dan keterangan selain izin atau dispen
        foreach ($kehadiran as $data) {
            if (
                is_null($data->waktu_datang) && is_null($data->waktu_pulang) &&
                !in_array($data->keterangan, ['izin', 'dispen', 'sakit'])
            ) {
                $data->keterangan = 'alpha';
                $data->save();
            }
        }

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

    public function getPresensiSiswa(Request $request)
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

    public function getPresensiGuru(Request $request)
    {
        $guru_id = Auth::user()->guru_id;
        $kelas_id = Guru::where('id', $guru_id)->value('kelas_id');
        $siswa_id = Siswa::where('kelas_id', $kelas_id)->value('id');
        $date = $request->input('date');
        $range = $request->input('range'); // 'week', 'month', or 'semester'

        if ($date) {
            $date = Carbon::parse($date)->startOfDay();
            $data = Kehadiran::where('siswa_id', $siswa_id)
                ->whereDate('tanggal', $date)
                ->get();
            return $this->success($data);
        } else {
            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);

            if ($range === 'week') {
                // Jika filter berdasarkan 1 minggu
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
            } elseif ($range === 'semester') {
                // Jika filter berdasarkan 6 bulan atau 1 semester
                $startDate = Carbon::now()->subMonths(6)->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
            } elseif ($range === 'month') {
                // Jika filter berdasarkan 1 bulan
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
            } elseif ($month) {
                // Jika `month` dan `year` diberikan, ambil awal dan akhir bulan tersebut
                $startDate = Carbon::create($year, $month)->startOfMonth();
                $endDate = Carbon::create($year, $month)->endOfMonth();
            } else {
                // Jika hanya `year` diberikan, ambil awal dan akhir tahun
                $startDate = Carbon::create($year)->startOfYear();
                $endDate = Carbon::create($year)->endOfYear();
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

        $data = Izin::where('siswa_id', $siswa_id)->where('jenis_izin', 'izin')->whereBetween('tanggal', [$startDate, $endDate])->get();
        return $this->success($data);
    }

    public function getSakit()
    {
        $siswa_id = Auth::user()->siswa_id;
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $data = Izin::where('siswa_id', $siswa_id)->where('jenis_izin', 'sakit')->whereBetween('tanggal', [$startDate, $endDate])->get();
        return $this->success($data);
    }

    // Old code for presensi
    // public function presensi(Request $request)
    // {
    //     $currentTime = Carbon::now('Asia/Jakarta');
    //     $tanggalFormatted = $currentTime->format('Y-m-d');
    //     $waktuPulang = Carbon::parse('10:00:00', 'Asia/Jakarta');
    //     $batasWaktuDatang = Carbon::parse('07:00:00', 'Asia/Jakarta');

    //     // Cek apakah ada input RFID, gunakan untuk mencari siswa
    //     if ($request->has('rfid')) {
    //         $siswa = Siswa::where('rfid_code', $request->rfid)->first();
    //         if (!$siswa) {
    //             return response()->json(['message' => 'RFID tidak valid'], 404);
    //         }
    //         $siswa_id = $siswa->id;
    //     } else {
    //         // Jika tidak ada RFID, gunakan ID dari akun yang login
    //         $siswa_id = Auth::user()->siswa_id;
    //     }

    //     // Periksa apakah siswa sudah melakukan presensi hari ini
    //     $absen = Kehadiran::where('siswa_id', $siswa_id)
    //         ->where('tanggal', $tanggalFormatted)
    //         ->first();

    //     if ($absen) {
    //         if ($absen->waktu_pulang) {
    //             return response()->json(['message' => 'Anda sudah absen pulang hari ini.'], 422);
    //         } elseif ($currentTime->greaterThanOrEqualTo($waktuPulang)) {
    //             $absen->update(['waktu_pulang' => $currentTime]);
    //             return response()->json(['status' => 'pulang', 'message' => 'Berhasil Absen  Pulang', 'data' => $absen], 201);
    //         } else {
    //             return response()->json(['message' => 'Belum waktunya pulang.'], 422);
    //         }
    //     } else {
    //         // Presensi datang jika belum dilakukan
    //         $keterangan = $currentTime->greaterThanOrEqualTo($batasWaktuDatang) ? 'telat' : 'hadir';
    //         $absen = Kehadiran::create([
    //             'siswa_id' => $siswa_id,
    //             'tanggal' => $tanggalFormatted,
    //             'keterangan' => $keterangan,
    //             'waktu_datang' => $currentTime,
    //         ]);
    //         return response()->json(['status' => 'datang', 'message' => 'Berhasil Absen  Datang', 'data' => $absen], 200);
    //     }

    //     // Setelah jam pulang, tetapkan siswa yang belum absen sebagai alpha
    //     if ($currentTime->greaterThanOrEqualTo($waktuPulang)) {
    //         $siswaBelumAbsen = Siswa::whereNotIn('id', function ($query) use ($tanggalFormatted) {
    //             $query->select('siswa_id')
    //                 ->from('kehadiran')
    //                 ->where('tanggal', $tanggalFormatted);
    //         })->get();

    //         foreach ($siswaBelumAbsen as $siswa) {
    //             Kehadiran::create([
    //                 'siswa_id' => $siswa->id,
    //                 'tanggal' => $tanggalFormatted,
    //                 'keterangan' => 'alpha',
    //                 'waktu_datang' => null,
    //                 'waktu_pulang' => null,
    //             ]);
    //         }
    //     }
    // }

    // new code for presensi
    public function presensi(Request $request)
    {
        $currentTime = Carbon::now('Asia/Jakarta');
        $tanggalFormatted = $currentTime->format('Y-m-d');
        $waktuPulang = Carbon::parse('15:00:00', 'Asia/Jakarta');
        $batasWaktuDatang = Carbon::parse('07:00:00', 'Asia/Jakarta');

        // Initialize class-based student data caching
        static $classStudents = null;
        static $classStudentsLoaded = false;

        // Check if we're initiating class attendance (teacher mode)
        if ($request->has('kelas_id') && !$classStudentsLoaded) {
            $classStudents = Siswa::where('kelas_id', $request->kelas_id)
                ->with('kelas')
                ->get()
                ->keyBy('rfid_code'); // Index by RFID for faster lookup

            $classStudentsLoaded = true;

            // Return the list of students (for teacher UI) without processing attendance
            return response()->json([
                'message' => 'Daftar siswa siap untuk presensi',
                'siswa' => $classStudents,
                'kelas' => $classStudents->first()->kelas->nama ?? null
            ], 200);
        }

        // Process RFID attendance
        if ($request->has('rfid')) {
            // Use preloaded students if available, otherwise fallback to database query
            if ($classStudents && $classStudents->has($request->rfid)) {
                $siswa = $classStudents[$request->rfid];
            } else {
                $siswa = Siswa::where('rfid_code', $request->rfid)->first();
                if (!$siswa) {
                    return response()->json(['message' => 'RFID tidak valid'], 404);
                }
            }

            $siswa_id = $siswa->id;
        } else {
            // If no RFID, use ID from logged in account
            $siswa_id = Auth::user()->siswa_id;
        }

        // Check if student already has attendance record today
        $absen = Kehadiran::where('siswa_id', $siswa_id)
            ->where('tanggal', $tanggalFormatted)
            ->first();

        if ($absen) {
            if ($absen->waktu_pulang) {
                return response()->json(['message' => 'Anda sudah absen pulang hari ini.'], 422);
            } elseif ($currentTime->greaterThanOrEqualTo($waktuPulang)) {
                $absen->update(['waktu_pulang' => $currentTime]);
                return response()->json([
                    'status' => 'pulang',
                    'message' => 'Berhasil Absen Pulang',
                    'data' => $absen,
                    'siswa' => $siswa
                ], 201);
            } else {
                return response()->json(['message' => 'Belum waktunya pulang.'], 422);
            }
        } else {
            // First attendance of the day
            $keterangan = $currentTime->greaterThanOrEqualTo($batasWaktuDatang) ? 'telat' : 'hadir';
            $absen = Kehadiran::create([
                'siswa_id' => $siswa_id,
                'tanggal' => $tanggalFormatted,
                'keterangan' => $keterangan,
                'waktu_datang' => $currentTime,
            ]);

            return response()->json([
                'status' => 'datang',
                'message' => 'Berhasil Absen Datang',
                'data' => $absen,
                'siswa' => $siswa
            ], 200);
        }

        // Mark absent students as alpha after school hours
        if ($currentTime->greaterThanOrEqualTo($waktuPulang)) {
            $siswaBelumAbsen = Siswa::whereNotIn('id', function ($query) use ($tanggalFormatted) {
                $query->select('siswa_id')
                    ->from('kehadiran')
                    ->where('tanggal', $tanggalFormatted);
            })->get();

            foreach ($siswaBelumAbsen as $siswa) {
                Kehadiran::create([
                    'siswa_id' => $siswa->id,
                    'tanggal' => $tanggalFormatted,
                    'keterangan' => 'alpha',
                    'waktu_datang' => null,
                    'waktu_pulang' => null,
                ]);
            }
        }
    }

    // Face recognition
    //  public function presensi(Request $request)
    // {
    //     $currentTime = Carbon::now('Asia/Jakarta');
    //     $tanggalFormatted = $currentTime->format('Y-m-d');
    //     $waktuPulang = Carbon::parse('15:00:00', 'Asia/Jakarta');
    //     $batasWaktuDatang = Carbon::parse('05:00:00', 'Asia/Jakarta');

    //     // Validate face recognition data
    //     $request->validate([
    //         'nama_siswa' => 'required|string',
    //         'kelas_id' => 'required|integer'
    //     ]);

    //     // Find student by name and class
    //     $siswa = Siswa::where('nama', $request->nama_siswa)
    //                  ->where('kelas_id', $request->kelas_id)
    //                  ->first();

    //     if (!$siswa) {
    //         return response()->json([
    //             'message' => 'Siswa tidak ditemukan',
    //             'suggestions' => Siswa::where('nama', 'like', '%'.$request->nama_siswa.'%')
    //                                 ->pluck('nama')
    //         ], 404);
    //     }

    //     $siswa_id = $siswa->id;

    //     // Check existing attendance
    //     $absen = Kehadiran::where('siswa_id', $siswa_id)
    //                      ->where('tanggal', $tanggalFormatted)
    //                      ->first();

    //     if ($absen) {
    //         if ($absen->waktu_pulang) {
    //             return response()->json([
    //                 'status' => 'already_done',
    //                 'message' => 'Anda sudah absen pulang hari ini'
    //             ], 422);
    //         } elseif ($currentTime->greaterThanOrEqualTo($waktuPulang)) {
    //             $absen->update(['waktu_pulang' => $currentTime]);
    //             return response()->json([
    //                 'status' => 'pulang',
    //                 'message' => 'Berhasil Absen Pulang',
    //                 'data' => $absen,
    //                 'siswa' => $siswa
    //             ], 200);
    //         } else {
    //             return response()->json([
    //                 'status' => 'too_early',
    //                 'message' => 'Belum waktunya pulang'
    //             ], 422);
    //         }
    //     } else {
    //         // First attendance of the day
    //         $keterangan = $currentTime->greaterThanOrEqualTo($batasWaktuDatang) ? 'telat' : 'hadir';

    //         $absen = Kehadiran::create([
    //             'siswa_id' => $siswa_id,
    //             'tanggal' => $tanggalFormatted,
    //             'keterangan' => $keterangan,
    //             'waktu_datang' => $currentTime,
    //             'metode' => 'face_recognition'
    //         ]);

    //         return response()->json([
    //             'status' => 'datang',
    //             'message' => 'Berhasil Absen Datang',
    //             'data' => $absen,
    //             'siswa' => $siswa
    //         ], 200);
    //     }
    // }

    public function reqIzin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image'      => 'required|image|mimes: jpeg,png,jpg,gif,svg|max: 2048',
            'jenis_izin' => 'required|in:sakit,izin',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'invalid field', 'error' => $validator->errors()], 422);
        }

        $siswa_id = Auth::user()->siswa_id;
        $tanggal = Carbon::now()->timezone('Asia/Jakarta')->format('Y-m-d');

        $existingIzin = Izin::where('siswa_id', $siswa_id)->whereDate('tanggal', $tanggal)->first();
        if ($existingIzin) {
            return $this->fail('Anda hanya dapat mengajukan izin satu kali per hari', 400);
        }

        $image = $request->file('image');
        $fileName = time() . '_' . $image->getClientOriginalName();
        $filePath = $image->storeAs('uploads', $fileName, 'public');

        $imageUpload = Image::create([
            'file_name' => $fileName,
            'file_path' => '/storage/app/public/' . $filePath
        ]);

        $izin = Izin::create([
            'siswa_id'   => $siswa_id,
            'image_id'   => $imageUpload->id,
            'tanggal'    => $tanggal,
            'jenis_izin' => $request->jenis_izin,
            'deskripsi'  => $request->deskripsi,  // optional
            'status'     => 'pending'
        ]);

        $kehadiran = Kehadiran::create([
            'siswa_id'     => $siswa_id,
            'tanggal'      => $tanggal,
            'keterangan'   => $request->jenis_izin,
            'waktu_datang' => null,
            'waktu_pulang' => null,
        ]);

        return response()->json(['message' => 'Siswa Berhasil Izin', 'data' => $izin], 200);
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
        $tanggal = Carbon::now()->timezone('Asia/Jakarta')->format('Y-m-d');

        if ($siswa_id == null) {
            return response()->json(['message' => 'Siswa tidak ditemukan'], 404);
        }

        $existingDispen = Dispen::where('siswa_id', $siswa_id)->whereDate('tanggal', $tanggal)->first();
        if ($existingDispen) {
            return $this->fail('Anda hanya dapat mengajukan dispen satu kali per hari', 400);
        };

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

    public function accIzin($id)
    {
        $izin = Izin::with('siswa')->find($id);

        if (!$izin) {
            return response()->json(['message' => 'Data izin tidak ditemukan'], 404);
        }

        if ($izin->status === 'approve') {
            return response()->json(['message' => 'Izin siswa sudah di-approve sebelumnya'], 400);
        }

        if ($izin->status !== 'pending') {
            return response()->json(['message' => 'Status izin tidak valid untuk di-approve'], 400);
        }

        $izin->update(['status' => 'approve']);

        return response()->json([
            'message' => 'Berhasil approve izin siswa ' . $izin->siswa->nama,
            'data' => $izin
        ], 200);
    }

    public function accDispen($id)
    {
        $approve = Dispen::with('siswa')->find($id);
        if (!$approve) {
            return response()->json(['message' => 'Data dispen tidak ditemukan'], 404);
        }

        if ($approve->status === 'approve') {
            return response()->json(['message' => 'Dispen siswa sudah di-approve sebelumnya'], 400);
        }

        if ($approve->status !== 'pending') {
            return response()->json(['message' => 'Status dispen tidak valid untuk di-approve'], 400);
        }

        $approve->update(['status' => 'approve']);
        return response()->json([
            'message' => 'Berhasil approve dispen siswa ' . $approve->siswa->nama,
            'data' => $approve
        ], 200);
    }

    public function checkAbsen()
    {
        // Pastikan user adalah siswa dan memiliki siswa_id
        if (!Auth::check() || !Auth::user()->siswa_id) {
            return response()->json(['message' => 'Siswa tidak ditemukan'], 404);
        }

        $siswa_id = Auth::user()->siswa_id;
        $tanggal = Carbon::now()->timezone('Asia/Jakarta')->startOfDay();
        // Cari data kehadiran untuk siswa pada hari ini
        $absen = Kehadiran::where('siswa_id', $siswa_id)
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($absen) {
            // Jika sudah ada absen
            if ($absen->waktu_pulang !== null) {
                return response()->json([
                    'message' => 'Anda sudah absen pulang hari ini',
                    'status' => 'pulang',
                    'data' => $absen
                ], 200);
            } elseif ($absen->keterangan === 'izin' || $absen->keterangan === 'dispen') {
                return response()->json([
                    'message' => 'Anda sudah mengajukan ' . $absen->keterangan . ' hari ini',
                    'status' => $absen->keterangan,
                    'data' => $absen
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Anda sudah absen datang hari ini',
                    'status' => 'datang',
                    'data' => $absen
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Anda belum absen hari ini',
                'status' => 'belum'
            ], 200);
        }
    }
}
