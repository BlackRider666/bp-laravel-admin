<?php
use Illuminate\Support\Facades\Route;
use BlackParadise\LaravelAdmin\Http\Controllers\AbstractController;

Route::group(['middleware' => ['web']], function () {
    Route::name('bpadmin.')->prefix('admin')->middleware('exists')->group(function () {
        foreach(config('bpadmin.entities') as $name => $value) {
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
})->name('bpadmin.pages.index')->middleware('web');
