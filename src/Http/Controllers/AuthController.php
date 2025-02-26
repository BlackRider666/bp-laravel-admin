<?php


namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Form;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\FormBuilder;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\EmailInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\PasswordInput;
use BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\PageBuilder;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class AuthController
{
    /**
     * @return View
     */
    public function getLoginPage(): View
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

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     * @throws Exception
     */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|string|email|exists:users,email',
            'password' => 'required|string|max:255',
        ]);
        $user = config('bpadmin.userEntity')::where('email', $data['email'])->first();
        if (! $user
            || ! Hash::check($data['password'], $user->password)

        ) {
            throw ValidationException::withMessages([
                'email' => 'Wrong credentials',
            ]);
        }
        if (!Auth::guard('web')->attempt($request->only('email', 'password'))) {
            throw new RuntimeException('Something wrong');
        }

        return redirect()->route('bpadmin.pages.index');
    }

    /**
     * @return RedirectResponse
     */
    public function logout(): RedirectResponse
    {
        Auth::guard('web')->logout();

        return redirect()->route('bpadmin.pages.index');
    }
}
