<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PreRegistrationController as AdminPreRegistrationController;
use App\Http\Controllers\Admin\ReauthenticateController;
use App\Http\Controllers\Admin\ResendConfirmationController as AdminResendConfirmationController;
use App\Http\Controllers\Admin\StatusController;
use App\Http\Controllers\CepController;
use App\Http\Controllers\EmailConfirmationController;
use App\Http\Controllers\PreRegistrationController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\ResendConfirmationController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/ready', ReadinessController::class)
    ->withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->name('readiness');
Route::get('/', [PreRegistrationController::class, 'create'])->name('pre-registration.create');
Route::post('/pre-cadastro', [PreRegistrationController::class, 'store'])->middleware('throttle:pre-registration')->name('pre-registration.store');
Route::post('/pre-cadastro/reenviar-confirmacao', ResendConfirmationController::class)->middleware('throttle:confirmation-resend')->name('pre-registration.resend');
Route::get('/pre-cadastro/recebido', [PreRegistrationController::class, 'success'])->name('pre-registration.success');
Route::get('/confirmar-email/{token}', [EmailConfirmationController::class, 'show'])->middleware('throttle:confirmation')->where('token', '[A-Za-z0-9_-]{43}')->name('pre-registration.confirm');
Route::post('/confirmar-email/{token}', [EmailConfirmationController::class, 'store'])->middleware('throttle:confirmation')->where('token', '[A-Za-z0-9_-]{43}')->name('pre-registration.confirm.store');
Route::get('/api/cep', CepController::class)->middleware('throttle:cep')->name('cep.lookup');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('/entrar', [AuthController::class, 'create'])->name('login');
        Route::post('/entrar', [AuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['auth:admin', 'active.admin'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/pre-cadastros/{preRegistration}', [AdminPreRegistrationController::class, 'show'])->whereUuid('preRegistration')->name('pre-registrations.show');
        Route::get('/pre-cadastros/{preRegistration}/midias/{media}', [MediaController::class, 'show'])->whereUuid(['preRegistration', 'media'])->name('media.show');
        Route::patch('/pre-cadastros/{preRegistration}/status', StatusController::class)->name('status.update');
        Route::post('/pre-cadastros/{preRegistration}/reenviar-confirmacao', AdminResendConfirmationController::class)->whereUuid('preRegistration')->middleware('throttle:admin-confirmation-resend')->name('confirmation.resend');
        Route::post('/exportar', ExportController::class)->middleware(['recent.admin.password', 'throttle:admin-export'])->name('export');
        Route::get('/reautenticar', [ReauthenticateController::class, 'create'])->name('reauthenticate');
        Route::post('/reautenticar', [ReauthenticateController::class, 'store'])->middleware('throttle:admin-reauthenticate')->name('reauthenticate.store');
        Route::post('/sair', [AuthController::class, 'destroy'])->name('logout');
    });
});
