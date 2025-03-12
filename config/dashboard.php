<?php

return [
    'title' => 'BPAdmin',
    'languages'     => ['en'],

    'entities' => [
        'users' => \App\Models\User::class,
    ],
    'menu' => [
        'users' => [
            'icon' => 'mdi-account-group',
            'items' => [
                'users' => [
                    'icon' => 'mdi-account-group',
                ],
            ],
        ],
    ],
    'custom_actions' => [
    ],
    'auth' => [
        'userEntity'    => \App\Models\User::class,
        'username'      => 'email',
        'custom_actions' => [
//            'loginPage' => \App\BPAdmin\Action\Auth\YourCustomAction::class,
//            'login' => \App\BPAdmin\Action\Auth\YourCustomAction::class,
//            'logout' => \App\BPAdmin\Action\Auth\YourCustomAction::class,
        ],
        'auth_rules'     => static function (array $credentials, \Illuminate\Contracts\Auth\Authenticatable $user) : bool {
            //your rules here
            //$user->hasRole('admin');
            return true;
        }
    ],
];
