<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // BUAT VALIDASI INPUTAN NAME, EMAIL, DAN PASSWORD
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid field', 'errors' => $validator->errors()]);
        }


        //CREATE DATA USER
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'siswa_id' => $request->siswa_id,
            'guru_id' => $request->guru_id,
        ]);

        //CREATE TOKEN BUAT LOGIN
        $token = $user->createToken('auth_token')->plainTextToken;

        //RESPONSE DATA NYA
        return response()->json(['message' => 'Berhasil buat akun baru', 'data' => $user, 'token' => $token], 200);
    }


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|numeric', // NISN atau NIP
            'password' => 'required|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid field', 'errors' => $validator->errors()]);
        }

        $user = User::whereHas('siswa', function ($query) use ($request) {
            $query->where('nisn', $request->identifier);
        })->orWhereHas('guru', function ($query) use ($request) {
            $query->where('nip', $request->identifier);
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'NISN/NIP or password incorrect'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['data' => $user, 'token' => $token], 200);
    }



    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil logout']);
    }
}
