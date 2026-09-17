<?php

use hexa_package_instagram\Domains\Accounts\Http\Controllers\InstagramAccountsController;
use hexa_package_instagram\Domains\Config\Http\Controllers\InstagramSettingsController;
use hexa_package_instagram\Domains\Workspace\Http\Controllers\InstagramWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {
    Route::get('/settings/instagram', [InstagramSettingsController::class, 'index'])->name('settings.instagram');
    Route::post('/settings/instagram', [InstagramSettingsController::class, 'save'])->name('settings.instagram.update');
    Route::post('/settings/instagram/test', [InstagramSettingsController::class, 'test'])->name('settings.instagram.test');
    Route::post('/settings/instagram/test-meta-token', [InstagramSettingsController::class, 'testMetaToken'])->name('settings.instagram.test-meta-token');

    Route::get('/instagram', [InstagramWorkspaceController::class, 'index'])->name('instagram.index');
    Route::get('/instagram/accounts', [InstagramAccountsController::class, 'index'])->name('instagram.accounts');
    Route::post('/instagram/accounts', [InstagramAccountsController::class, 'store'])->name('instagram.accounts.store');
    Route::post('/instagram/accounts/activate', [InstagramAccountsController::class, 'activate'])->name('instagram.accounts.activate');
    Route::get('/instagram/accounts/status', [InstagramAccountsController::class, 'status'])->name('instagram.accounts.status');
    Route::delete('/instagram/accounts', [InstagramAccountsController::class, 'destroy'])->name('instagram.accounts.destroy');

    Route::get('/instagram/raw', [InstagramWorkspaceController::class, 'raw'])->name('instagram.raw');
    Route::get('/instagram/status', [InstagramWorkspaceController::class, 'status'])->name('instagram.status');
    Route::get('/instagram/logs', [InstagramWorkspaceController::class, 'logs'])->name('instagram.logs');
    Route::post('/instagram/profile-scan', [InstagramWorkspaceController::class, 'profileScan'])->name('instagram.profile-scan');
    Route::post('/instagram/story-scan', [InstagramWorkspaceController::class, 'storyScan'])->name('instagram.story-scan');
    Route::post('/instagram/post-scan', [InstagramWorkspaceController::class, 'postScan'])->name('instagram.post-scan');
    Route::post('/instagram/import-post', [InstagramWorkspaceController::class, 'importPost'])->name('instagram.import-post');
});
