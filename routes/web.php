<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\MfaController;
use App\Http\Controllers\Admin\StatusController;
use App\Http\Controllers\CepController;
use App\Http\Controllers\EmailConfirmationController;
use App\Http\Controllers\PreRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PreRegistrationController::class, 'create'])->name('pre-registration.create');
Route::post('/pre-cadastro', [PreRegistrationController::class, 'store'])->middleware('throttle:pre-registration')->name('pre-registration.store');
Route::get('/pre-cadastro/recebido', [PreRegistrationController::class, 'success'])->name('pre-registration.success');
Route::get('/confirmar-email/{token}', EmailConfirmationController::class)->middleware('throttle:confirmation')->where('token', '[A-Za-z0-9_-]{43}')->name('pre-registration.confirm');
Route::get('/api/cep', CepController::class)->middleware('throttle:cep')->name('cep.lookup');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('/entrar', [AuthController::class, 'create'])->name('login');
        Route::post('/entrar', [AuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
        Route::get('/mfa', [MfaController::class, 'create'])->name('mfa.create');
        Route::post('/mfa', [MfaController::class, 'store'])->middleware('throttle:admin-mfa')->name('mfa.store');
    });

    Route::middleware(['auth:admin', 'active.admin'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::patch('/pre-cadastros/{preRegistration}/status', StatusController::class)->name('status.update');
        Route::get('/exportar', ExportController::class)->middleware('throttle:admin-export')->name('export');
        Route::post('/sair', [AuthController::class, 'destroy'])->name('logout');
    });
});
