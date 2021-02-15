<?php
use Illuminate\Support\Facades\Route;
use BlackParadise\Admin\CRUD\Http\Controllers\AbstractController;
Route::group(['middleware' => ['web']], function () {
    Route::name('bpadmin.')->prefix('admin')->middleware('auth')->group(function () {
        Route::get('/', function () {
            return view('bpadmin::pages.index');
        })->name('pages.index');
        Route::get('/{name}/', [AbstractController::class,'index']);
        Route::get('/{name}/create', [AbstractController::class,'create']);
        Route::post('/{name}/', [AbstractController::class,'store']);
        Route::get('/{name}/{id}', [AbstractController::class,'show']);
        Route::get('/{name}/{id}/edit', [AbstractController::class,'edit']);
        Route::put('/{name}/{id}', [AbstractController::class,'update']);
        Route::delete('/{name}/{id}', [AbstractController::class,'destroy']);
    });
});
