<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Admin\AdminSub;
use App\Models\HakaksesMenu;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    public function get_user()
    {
        $data = User::where('username','!=' ,'sa')
        ->whereNotNull('jabatan')
        ->when(request('q') , function($x){
            $x->where('nama', 'like', '%' . request('q') . '%')
              ->orWhere('username','like', '%' . request('q') . '%')
              ->orWhere('jabatan','like', '%' . request('q') . '%');
        })

        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($data);
    }
    public function save_user(Request $request)
    {
        $id = $request->input('id');

        // Cek apakah ID pengguna ada
        $user = User::find($id);
        if (!$user && !$request->input('id')) {
            // Pengguna tidak ditemukan, buat baru
            $validator = Validator::make($request->all(), [
                'nama' => 'required',
                'email' => 'required|email|unique:users',
                'username' => 'required|unique:users',
                'password' => 'required',
            ]);
        } else {
            // Pengguna ditemukan, update
            $validator = Validator::make($request->all(), [
                'nama' => 'required',
                'email' => 'required|email|unique:users,email,' . $id,
                'username' => 'required|unique:users,username,' . $id,
                'password' => 'required',
            ]);
        }

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Simpan atau update pengguna
        if ($user) {
            $user->update([
                'email' => $request->email,
                'nama' => $request->nama,
                'password' => bcrypt($request->password),
                'jabatan' => $request->jabatan,
                'kodejabatan' => $request->kodejabatan,
                'nohp' => $request->nohp,
                'alamat' => $request->alamat,
            ]);
        } else {
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'nama' => $request->nama,
                'password' => bcrypt($request->password),
                'jabatan' => $request->jabatan,
                'kodejabatan' => $request->kodejabatan,
                'nohp' => $request->nohp,
                'alamat' => $request->alamat,
            ]);
        }

        if (!$user) {
            return response()->json(['message' => 'Maaf, Data User Gagal Disimpan!'], 500);
        }

        return response()->json([
            'message' => 'Data Berhasil Disimpan...!',
            'result' => $user
        ], 200);
    }


    public function remove_user(Request $request)
    {
        $removedata = User::find($request->id);
        if (!$removedata) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 501);
        }
        $removedata->delete();

        return new JsonResponse(['message' => 'Berhasil Dihapus'], 200);
    }

    public function getdatasetiing()
    {
        $data = User::with(
            [
                'hakakses.menus',
                'hakakses.subs'
            ]
        )
        ->where('username', '<>', 'sa')
        // ->when(request('q') !== '' || request('q') !== null, function($x){
        //     $x->where('nama', 'like', '%' . request('q') . '%')
        //       ->orWhere('username','like', '%' . request('q') . '%')
        //       ->orWhere('jabatan','like', '%' . request('q') . '%');
        // })

        ->orderBy('id', 'desc')
        ->get();
        return new JsonResponse($data);
    }

    public function getmenu()
    {
        $data = AdminSub::with('menus')->get();
        return new JsonResponse($data);
    }

    public function savehakakses(Request $request)
    {
        $cek = HakaksesMenu::where('submenu_id', $request->submenu)->where('user_id', $request->userid)->count();
        if($cek > 0)
        {
            return new JsonResponse(['message' => 'Data Sudah Ada'], 500);
        }
        $simpan = HakaksesMenu::create(
            [
                'user_id' => $request->userid,
                'menu_id' => $request->menu,
                'submenu_id' => $request->submenu
            ]
        );

        $data = User::with(
            [
                'hakakses.menus',
                'hakakses.subs'
            ]
        )
        ->where('id', $request->userid)
        ->orderBy('id', 'desc')
        ->get();
        return new JsonResponse(['message' => 'Data Berhasil Disimpan...!!!', 'result' => $data], 200);
    }
}
