<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'index'])->name('portal');
Route::get('/convocatorias/{recruitmentCall}', [PortalController::class, 'call'])->name('calls.show');
Route::get('/plazas/{vacancy}', [PortalController::class, 'show'])->name('vacancies.show');
Route::get('/resultados', [PortalController::class, 'results'])->name('results');
Route::get('/ayuda', [PortalController::class, 'help'])->name('help');
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthController::class, 'login'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'authenticate'])->middleware('throttle:login');
    Route::get('/registro', [AuthController::class, 'register'])->name('register');
    Route::post('/registro', [AuthController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/recuperar-acceso', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/recuperar-acceso', [AuthController::class, 'sendReset'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/restablecer/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/restablecer', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');
    Route::get('/panel', [ManagementController::class, 'dashboard'])->name('dashboard');
    Route::get('/perfil', [ManagementController::class, 'profile'])->name('profile');
    Route::put('/perfil', [ManagementController::class, 'updateProfile'])->name('profile.update');
    Route::get('/documentos/{document}', [ApplicationController::class, 'document'])->name('documents.show');
    Route::middleware('role:postulante')->group(function () {
        Route::get('/mis-postulaciones', [ApplicationController::class, 'index'])->name('applications.index');
        Route::post('/plazas/{vacancy}/postular', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('/mis-postulaciones/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::put('/mis-postulaciones/{application}/formulario', [ApplicationController::class, 'saveForm'])->name('applications.form');
        Route::put('/mis-postulaciones/{application}', [ApplicationController::class, 'update'])->name('applications.update');
        Route::post('/mis-postulaciones/{application}/documentos', [ApplicationController::class, 'upload'])->middleware('throttle:30,1')->name('applications.upload');
        Route::post('/mis-postulaciones/{application}/enviar', [ApplicationController::class, 'submit'])->name('applications.submit');
        Route::get('/mis-postulaciones/{application}/cargo', [ApplicationController::class, 'receipt'])->name('applications.receipt');
    });
    Route::middleware('role:administrador,gestor,jurado')->group(function () {
        Route::get('/expedientes', [ManagementController::class, 'applications'])->name('review.index');
        Route::get('/expedientes/{application}', [ReviewController::class, 'show'])->name('review.show');
    });
    Route::middleware('role:jurado')->group(function () {
        Route::post('/expedientes/{application}/aptitud', [ReviewController::class, 'eligibility'])->name('review.eligibility');
        Route::post('/expedientes/{application}/evaluacion', [ReviewController::class, 'evaluate'])->name('review.evaluate');
    });
    Route::middleware('role:administrador,gestor')->prefix('gestion')->group(function () {
        Route::get('/convocatorias', [ManagementController::class, 'calls'])->name('management.calls');
        Route::get('/convocatorias/nueva', [ManagementController::class, 'create'])->name('management.calls.create');
        Route::post('/convocatorias', [ManagementController::class, 'store'])->name('management.calls.store');
        Route::get('/convocatorias/{recruitmentCall}', [ManagementController::class, 'show'])->name('management.calls.show');
        Route::get('/convocatorias/{recruitmentCall}/editar', [ManagementController::class, 'edit'])->name('management.calls.edit');
        Route::put('/convocatorias/{recruitmentCall}', [ManagementController::class, 'update'])->name('management.calls.update');
        Route::post('/convocatorias/{recruitmentCall}/plazas', [ManagementController::class, 'vacancy'])->name('management.vacancies.store');
        Route::post('/convocatorias/{recruitmentCall}/cerrar-prueba', [ManagementController::class, 'closeDemo'])->name('management.demo.close');
        Route::post('/convocatorias/{recruitmentCall}/etapa', [ManagementController::class, 'transition'])->name('management.calls.transition');
        Route::put('/plazas/{vacancy}/formulario', [ManagementController::class, 'configureScreening'])->name('management.screening');
        Route::put('/plazas/{vacancy}/jurados', [ManagementController::class, 'assign'])->name('management.jurors');
        Route::get('/auditoria', [ManagementController::class, 'audit'])->name('audit');
    });
    Route::middleware('role:administrador')->prefix('gestion')->group(function () {
        Route::get('/usuarios', [ManagementController::class, 'users'])->name('users');
        Route::post('/usuarios', [ManagementController::class, 'createUser'])->name('users.store');
    });
});
