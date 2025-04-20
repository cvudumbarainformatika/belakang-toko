<?php

namespace App\Http\Controllers\Api\login;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth as FacadesJWTAuth;

class loginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username'  => 'required',
            'password'  => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $credentials = $request->only('username', 'password');

        if (!$token = FacadesJWTAuth::attempt($credentials, ['exp' => Carbon::now()->addDays(7)->timestamp])) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau Password Anda salah'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => auth()->user(),
            'token'   => $token
        ], 200);
    }
}
