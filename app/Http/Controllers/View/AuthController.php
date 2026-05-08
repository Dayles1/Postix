<?php

namespace App\Http\Controllers\View;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
{
    $credentials = $request->validate([
        'email'    => 'required',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return back()->withErrors([
            'email' => __('messages.login.error'),
        ]);
    }

    $request->session()->regenerate();

    $user = Auth::user()->loadMissing([
        'avatar',
        'role',
        'department',
    ]);

    // ❌ User ban tekshiruvi
    $userBan = DB::table('bans')
        ->where('bannable_type', \App\Models\User::class)
        ->where('bannable_id', $user->id)
        ->where('active', true)
        ->exists();

    

    if ($userBan ) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return back()->withErrors([
            'email' => __('messages.admin.banned'),
        ]);
    }

    return redirect('/');
}

    public function logout()
    {
        Auth::logout();

        return redirect('/login');
    }
}
