<?php

use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Auth\{LoginActionInterface,
    LoginPageActionInterface,
    LogoutActionInterface
};
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity\{UpdateEntityInterface,
    CreateEntityInterface,
    DeleteEntityInterface,
    EditEntityInterface,
    IndexEntityInterface,
    ShowEntityInterface,
    StoreEntityInterface};
use Illuminate\Support\Facades\Route;

Route::group([
    'as' => 'bpadmin.',
    'prefix' => 'admin',
    'middleware' => ['web'],
],function () {
    Route::group(['middleware' => ['admin-auth','exists']], function () {
        foreach (config('bpadmin.entities') as $name => $value) {
            Route::name($name . '.')->prefix($name)->group(function () {
                Route::get('/', IndexEntityInterface::class)->name('index');
                Route::get('/create', CreateEntityInterface::class)->name('create');
                Route::post('/', StoreEntityInterface::class)->name('store');
                Route::get('/{id}', ShowEntityInterface::class)->name('show');
                Route::get('/{id}/edit', EditEntityInterface::class)->name('edit');
                Route::put('/{id}', UpdateEntityInterface::class)->name('update');
                Route::delete('/{id}', DeleteEntityInterface::class)->name('destroy');
            });
        }
    });
    Route::get('/', function () {
        return view('bpadmin::pages.index');
    })->name('pages.index')->middleware('admin-auth');
    Route::group([
        'as' => 'auth.',
        'prefix' => 'auth',
    ], static function () {
        Route::get('login', LoginPageActionInterface::class)
            ->name('login');
        Route::post('login', LoginActionInterface::class)
            ->name('loginPost');
        Route::post('logout', LogoutActionInterface::class)
            ->name('logout');
    });
});
