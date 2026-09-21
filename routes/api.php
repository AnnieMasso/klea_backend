<?php

use App\Http\Controllers\API\authController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\bienController;
use App\Http\Controllers\API\conditionController;
use App\Http\Controllers\API\contratController;
use App\Http\Controllers\API\conversationController;
use App\Http\Controllers\API\departController;
use App\Http\Controllers\API\imageController;
use App\Http\Controllers\API\messageController;
use App\Http\Controllers\API\paiementController;
use App\Http\Controllers\API\signalementController;
use App\Http\Controllers\API\sinistreController;
use App\Http\Controllers\API\usageController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [authController::class, 'register']);
    Route::post('/login', [authController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [authController::class, 'me']);
        Route::post('/confirm-identity', [authController::class, 'confirmIdentity']);
        Route::post('/change-password', [authController::class, 'changePassword']);
        Route::put('/profile', [authController::class, 'updateProfile']);
        Route::post('/logout', [authController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'bailleur.validated'])->group(function (): void {
    Route::apiResource('biens', bienController::class);
    Route::apiResource('signalements', signalementController::class);
    Route::apiResource('images', imageController::class);
    Route::apiResource('conversations', conversationController::class);
    Route::apiResource('messages', messageController::class);
    Route::apiResource('contrats', contratController::class);
    Route::apiResource('departs', departController::class);
    Route::apiResource('sinistres', sinistreController::class);
    Route::apiResource('usages', usageController::class);
    Route::apiResource('paiements', paiementController::class);
    Route::apiResource('conditions', conditionController::class);

    Route::patch('/contrats/{id}/signer', [contratController::class, 'signer']);
    Route::patch('/contrats/{id}/annuler', [contratController::class, 'annuler']);
    Route::patch('/contrats/{id}/resilier', [contratController::class, 'resilier']);
    Route::patch('/departs/{id}/confirmer', [departController::class, 'confirmer']);

    Route::prefix('admin')->group(function (): void {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/pending/users', [AdminController::class, 'pendingUsers']);
        Route::patch('/users/{id}/valider', [AdminController::class, 'validateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        Route::get('/pending/biens', [AdminController::class, 'pendingBiens']);
        Route::get('/biens', [AdminController::class, 'allBiens']);
        Route::patch('/biens/{id}/valider', [AdminController::class, 'validateBien']);
        Route::delete('/biens/{id}', [AdminController::class, 'deleteBien']);

        Route::get('/bailleurs', [AdminController::class, 'bailleursWithBiens']);
        Route::get('/locataires', [AdminController::class, 'locatairesWithOccupiedBien']);
    });
});
