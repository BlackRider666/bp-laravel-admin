<?php


namespace BlackParadise\LaravelAdmin\Http\Controllers;


use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class AuthController
{
    public function getLoginPage()
    {
        return view('bpadmin::pages.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|exists:users,email',
            'password' => 'required|string|max:255',
        ]);
        $user = User::where('email', $data['email'])->first();
        if (! $user
            || ! Hash::check($data['password'], $user->password)
            || ! $user->hasRole('superadmin')
        ) {
            throw ValidationException::withMessages([
                'email' => 'Wrong credentials',
            ]);
        }
        if (Auth::guard('web')->attempt($request->only('email', 'password'))) {
            return redirect()->route('bpadmin.pages.index');
        }
        throw Exception::error(['something wrong']);
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        return redirect()->route('bpadmin.pages.index');
    }
}
