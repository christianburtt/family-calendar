<?php

use App\Medical\Http\Controllers\DosarController;
use App\Medical\Http\Controllers\MedicalDocumentController;
use App\Medical\Http\Controllers\MedicalModuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Medical routes
|--------------------------------------------------------------------------
|
| Everything here sits behind the household's module switch and the viewer's
| membership of it. The guard resolves the calendar once and hangs it on the
| request, so no controller has to decide which household it is serving.
|
*/

Route::middleware(['auth', 'verified', 'medical.enabled'])->group(function (): void {
    Route::get('medical', [DosarController::class, 'index'])->name('medical.index');
    Route::get('medical/trash', [MedicalDocumentController::class, 'trashed'])->name('medical.trash');

    Route::get('medical/dosars/create', [DosarController::class, 'create'])->name('medical.dosars.create');
    Route::post('medical/dosars', [DosarController::class, 'store'])->name('medical.dosars.store');
    Route::get('medical/dosars/{dosar}', [DosarController::class, 'show'])->name('medical.dosars.show');
    Route::patch('medical/dosars/{dosar}', [DosarController::class, 'update'])->name('medical.dosars.update');
    Route::delete('medical/dosars/{dosar}', [DosarController::class, 'destroy'])->name('medical.dosars.destroy');
    Route::post('medical/dosars/{dosar}/restore', [DosarController::class, 'restore'])->name('medical.dosars.restore');

    Route::post('medical/dosars/{dosar}/documents', [MedicalDocumentController::class, 'store'])
        ->name('medical.documents.store');

    Route::get('medical/documents/{document}', [MedicalDocumentController::class, 'show'])
        ->name('medical.documents.show');
    Route::get('medical/documents/{document}/file', [MedicalDocumentController::class, 'file'])
        ->name('medical.documents.file');
    Route::get('medical/documents/{document}/download', [MedicalDocumentController::class, 'download'])
        ->name('medical.documents.download');
    Route::get('medical/documents/{document}/thumbnail', [MedicalDocumentController::class, 'thumbnail'])
        ->name('medical.documents.thumbnail');
    Route::patch('medical/documents/{document}', [MedicalDocumentController::class, 'update'])
        ->name('medical.documents.update');
    Route::delete('medical/documents/{document}', [MedicalDocumentController::class, 'destroy'])
        ->name('medical.documents.destroy');
    Route::post('medical/documents/{document}/retry', [MedicalDocumentController::class, 'retry'])
        ->name('medical.documents.retry');
    Route::post('medical/documents/{document}/restore', [MedicalDocumentController::class, 'restore'])
        ->name('medical.documents.restore');
});

/*
 * Switching the module on has to work while it is still off, so this route
 * sits outside the gate it controls. Authorisation is the calendar's owner,
 * not membership of an already-enabled household.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::patch('calendars/{calendar}/modules', [MedicalModuleController::class, 'update'])
        ->name('calendars.modules.update');
});
