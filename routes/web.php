<?php

use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\{CreateEntityInterface,
    DeleteEntityInterface,
    EditEntityInterface,
    IndexEntityInterface,
    ShowEntityInterface,
    StoreEntityInterface,
    UpdateEntityInterface};
use Illuminate\Support\Facades\Route;
use BlackParadise\LaravelAdmin\Http\Controllers\AuthController;

Route::group(['middleware' => ['web','admin-auth']], function () {
    Route::name('bpadmin.')->prefix('admin')->middleware('exists')->group(function () {
        foreach(config('bpadmin.entities') as $name => $value) {
            Route::name($name.'.')->prefix($name)->group(function() {
                Route::get('/', IndexEntityInterface::class)->name('index');
                Route::get('/create',CreateEntityInterface::class)->name('create');
                Route::post('/',StoreEntityInterface::class)->name('store');
                Route::get('/{id}',ShowEntityInterface::class)->name('show');
                Route::get('/{id}/edit',EditEntityInterface::class)->name('edit');
                Route::put('/{id}',UpdateEntityInterface::class)->name('update');
                Route::delete('/{id}',DeleteEntityInterface::class)->name('destroy');
            });
        }
    });
});
Route::get('/admin/', function () {
    return view('bpadmin::pages.index');
})->name('bpadmin.pages.index')->middleware(['web','admin-auth']);
Route::get('/admin/login/', [AuthController::class,'getLoginPage'])
    ->name('bpadmin.login')
    ->middleware('web');
Route::post('admin/login/', [AuthController::class,'login'])
    ->name('bpadmin.loginPost')
    ->middleware('web');
Route::post('admin/logout/', [AuthController::class,'logout'])
    ->name('bpadmin.logout')
    ->middleware('web');
