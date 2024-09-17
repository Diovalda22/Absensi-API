<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KehadiranController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\SiswaController;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('presensi', [PresensiController::class, 'getPresensi']);
    Route::get('izin', [PresensiController::class, 'getIzin']);
    Route::get('sakit', [PresensiController::class, 'getSakit']);
    Route::get('absen', [PresensiController::class, 'getAbsen']);
    Route::get('check', [PresensiController::class, 'checkAbsen']);

    Route::post('presensi', [PresensiController::class, 'presensi']);
    Route::post('izin', [PresensiController::class, 'reqIzin']);
    Route::put('izin/{id}', [PresensiController::class, 'accIzin'])->middleware('IsAdmin');
    Route::post('dispen', [PresensiController::class, 'reqDispen']);
    Route::put('dispen/{id}', [PresensiController::class, 'accDispen'])->middleware('IsAdmin');
    Route::resource('siswa', SiswaController::class)->middleware('IsAdmin');
});
