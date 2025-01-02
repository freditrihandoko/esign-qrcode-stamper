<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PdfStampController;

Volt::route('/login', 'login')->name('login');
Volt::route('/register', 'register');
Volt::route('/signed/{uniqueHash}', 'signed.index')->name('signed.verify');

Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
});

Route::middleware(['auth'])->group(function () {
    Volt::route('/', 'index');

    // Routes for approver
    Route::middleware(['role:approver,superadmin,admin'])->group(function () {
        Volt::route('/documents/type', 'documents.type')->name('documents.type');
        Volt::route('/qrcodes', 'qrcodes.index')->name('qrcodes.index');
        Volt::route('/qrcodes/create', 'qrcodes.create')->name('qrcodes.create');
        Volt::route('/qrcodes/generate', 'qrcodes.generate')->name('qrcodes.generate');
        Volt::route('/qrcodes/{qrCode}', 'qrcodes.show')->name('qrcodes.show');
        Route::get('pdfstamp/{qrCode}', [PdfStampController::class, 'show'])->name('pdfstamp.show');
        Route::post('pdfstamp/{qrCode}', [PdfStampController::class, 'post']);
    });

    // Routes for superadmin and admin only
    Route::middleware(['role:superadmin,admin'])->group(function () {
        Volt::route('/users', 'users.index');
        Volt::route('/users/create', 'users.create');
        Volt::route('/users/{user}/edit', 'users.edit');
        Volt::route('/departments', 'departments.index')->name('departments.index');
        Volt::route('/settings', 'settings.index')->name('settings.index');
    });

    // Routes accessible by all roles
    Volt::route('profile', 'profile.index')->name('profile.index');
    Volt::route('documents', 'documents.index')->name('documents.index');
    Volt::route('documents/{document}', 'documents.show')->name('documents.show');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/download-signed', [DocumentController::class, 'downloadSigned'])->name('documents.download-signed');
    Volt::route('/documents-pengajuan', 'documents-pengajuan.index')->name('documents-pengajuan.index');
    Volt::route('/documents-pengajuan/{document}', 'documents-pengajuan.detail')->name('documents-pengajuan.detail');

    // Routes for pimpinan
    Route::middleware(['role:pimpinan,superadmin,admin'])->group(function () {
        Volt::route('/documents-approval', 'documents-approval.index')->name('documents-approval.index');
    });
});
