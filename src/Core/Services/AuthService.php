<?php

namespace BlackParadise\LaravelAdmin\Core\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthService
{
    /**
     * @param array $credentials
     * @return void
     * @throws ValidationException
     */
    public function login(array $credentials): void
    {
        $username = config('bpadmin.auth.username');
        $user = config('bpadmin.auth.userEntity')::where($username, $credentials[$username])->first();

        if (! $user || ! $this->checkAuth($credentials, $user)) {
            throw ValidationException::withMessages([
                'email' => 'Wrong credentials',
            ]);
        }
        if (!Auth::guard('web')->attempt($credentials)) {
            throw new RuntimeException('Something wrong');
        }
    }

    /**
     * @param array $credentials
     * @param Authenticatable $user
     * @return bool
     */
    private function checkAuth(array $credentials, Authenticatable $user): bool
    {
        if (!Hash::check($credentials['password'], $user->password)) {
            return false;
        }

        $authRules = config('bpadmin.auth.auth_rules');

        if (is_callable($authRules)) {
            return (bool)$authRules($credentials, $user);
        }

        return true;
    }

    /**
     * @return void
     */
    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}
