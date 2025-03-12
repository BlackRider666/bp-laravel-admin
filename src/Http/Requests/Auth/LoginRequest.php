<?php

namespace BlackParadise\LaravelAdmin\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            config('bpadmin.auth.username') => 'required|string|email|exists:users,'.config('bpadmin.auth.username'),
            'password' => 'required|string|max:255',
        ];
    }
}
