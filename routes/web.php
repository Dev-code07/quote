<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\QuoteTemplateController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
     * Global topbar search across quotations and clients. Reuses each model's
     * scopeSearch() so shell search and list filters stay consistent.
     */
    Route::get('/search', [SearchController::class, 'index'])->name('search');

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
    /*
     * Templates - Phase 3 (PRD FR-04).
     * Restore and force-delete live in the trash namespace so a normal id can
     * never purge a record (PRD FR-12, decision #8).
     */
    // Live A4 preview for the editor. Declared before the resource routes so
    // 'templates/preview' is never read as a template id.
    Route::post('templates/preview', [QuoteTemplateController::class, 'preview'])->name('templates.preview');
    Route::post('templates/{template}/duplicate', [QuoteTemplateController::class, 'duplicate'])->name('templates.duplicate');
    Route::post('templates/{template}/set-default', [QuoteTemplateController::class, 'setDefault'])->name('templates.set-default');
    Route::get('templates-trash', [QuoteTemplateController::class, 'trash'])->name('templates.trash');
    Route::post('templates-trash/{template}/restore', [QuoteTemplateController::class, 'restore'])->name('templates.restore');
    Route::delete('templates-trash/{template}', [QuoteTemplateController::class, 'forceDestroy'])->name('templates.force-destroy');
    Route::resource('templates', QuoteTemplateController::class)->except(['destroy']);
    Route::delete('templates/{template}', [QuoteTemplateController::class, 'destroy'])->name('templates.destroy');

    /*
     * Quotations - Phase 4/5/6 (PRD FR-05, FR-10, FR-11).
     */
    Route::get('quotes/create/template', [QuoteController::class, 'create'])->name('quotes.create');
    Route::get('quotes/build', [QuoteController::class, 'build'])->name('quotes.build');
    Route::post('quotes/calculate', [QuoteController::class, 'calculate'])->name('quotes.calculate');

    Route::get('quotes/{quote}/pdf', [QuotePdfController::class, 'download'])->name('quotes.pdf');
    Route::get('quotes/{quote}/pdf/view', [QuotePdfController::class, 'show'])->name('quotes.pdf.view');
    Route::post('quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
    Route::patch('quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->name('quotes.status');

    Route::get('quotes-trash', [QuoteController::class, 'trash'])->name('quotes.trash');
    Route::post('quotes-trash/{quote}/restore', [QuoteController::class, 'restore'])->name('quotes.restore');
    Route::delete('quotes-trash/{quote}', [QuoteController::class, 'forceDestroy'])->name('quotes.force-destroy');

    Route::resource('quotes', QuoteController::class)->except(['create', 'destroy']);
    Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy');
});

require __DIR__.'/auth.php';
