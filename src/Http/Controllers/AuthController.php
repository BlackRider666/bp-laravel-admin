<?php


namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\FormBuilder\Form;
use BlackParadise\LaravelAdmin\Core\FormBuilder\FormBuilder;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\EmailInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\PasswordInput;
use BlackParadise\LaravelAdmin\Core\PageBuilder\PageBuilder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class AuthController
{
    public function getLoginPage()
    {
        $form = new Form([
            'action'    =>  route('bpadmin.loginPost'),
            'method'    =>  'POST',
            'submit_label' => trans('bpadmin::auth.btn.login')
        ]);
        $form->addField(new EmailInput(['name' => 'email', 'required' => true],'auth',[]));
        $form->addField(new PasswordInput(['name' => 'password', 'required' => true],'auth',[]));
        return (new PageBuilder('bpadmin::layout.auth',config('bpadmin.title'),[

            (new FormBuilder($form))->render(),
        ]))->render();
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|exists:users,email',
            'password' => 'required|string|max:255',
        ]);
        $user = config('bpadmin.userEntity')::where('email', $data['email'])->first();
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
