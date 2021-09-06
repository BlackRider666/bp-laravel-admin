<?php
use Illuminate\Support\Facades\Route;
use BlackParadise\LaravelAdmin\Http\Controllers\AbstractController;
use BlackParadise\LaravelAdmin\Http\Controllers\AuthController;
Route::group(['middleware' => ['web','admin-auth']], function () {
    Route::name('bpadmin.')->prefix('admin')->middleware('exists')->group(function () {
        foreach(config('bpadmin.dashboard.entities') as $name => $value) {
            Route::name($name.'.')->prefix($name)->group(function() {
                Route::get('/', [AbstractController::class,'index'])->name('index');
                Route::get('/create', [AbstractController::class,'create'])->name('create');
                Route::post('/', [AbstractController::class,'store'])->name('store');
                Route::get('/{id}', [AbstractController::class,'show'])->name('show');
                Route::get('/{id}/edit', [AbstractController::class,'edit'])->name('edit');
                Route::put('/{id}', [AbstractController::class,'update'])->name('update');
                Route::delete('/{id}', [AbstractController::class,'destroy'])->name('destroy');
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
