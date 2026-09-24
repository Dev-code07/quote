<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
     * Clients - Phase 2 (PRD FR-03).
     * Every delete is a soft delete; restore and force-delete live in the trash
     * namespace so a normal id can never be used to purge a record
     * (PRD FR-12, decision #8).
     */
    Route::resource('clients', ClientController::class)->except(['create', 'edit', 'destroy']);
    Route::patch('clients/{client}/status', [ClientController::class, 'toggleStatus'])->name('clients.toggle-status');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    Route::get('clients-trash', [ClientController::class, 'trash'])->name('clients.trash');
    Route::post('clients-trash/{client}/restore', [ClientController::class, 'restore'])->name('clients.restore');
    Route::delete('clients-trash/{client}', [ClientController::class, 'forceDestroy'])->name('clients.force-destroy');
    // Placeholders so the sidebar shell is fully navigable. Replaced by resource
    // controllers in Phase 3 (templates) and Phase 4 (quotes).
    Route::view('/templates', 'templates.index')->name('templates.index');
    Route::view('/quotes', 'quotes.index')->name('quotes.index');
});

require __DIR__.'/auth.php';
