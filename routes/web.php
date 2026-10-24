<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Manage\OfferPageController;
use App\Http\Controllers\Manage\ParticipantController;
use App\Http\Controllers\Manage\WishController;
use App\Http\Controllers\MyBookingsController;
use App\Http\Controllers\OfferCatalogController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\WishSubmissionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/angebote')->name('home');

Route::get('/angebote', [OfferCatalogController::class, 'index'])->name('offers.index');
Route::get('/angebote/{offer}', [OfferCatalogController::class, 'show'])->name('offers.show');
Route::get('/meine-termine', [MyBookingsController::class, 'index'])->name('bookings.mine');
Route::get('/datenschutz', PrivacyController::class)->name('privacy');

// Mitarbeitende: buchen, stornieren, umbuchen
Route::post('/angebote/{offer}/termine/{slot}/buchen', [BookingController::class, 'store'])->name('bookings.store');
Route::post('/buchungen/{booking}/stornieren', [BookingController::class, 'cancel'])->name('bookings.cancel');
Route::post('/buchungen/{booking}/umbuchen', [BookingController::class, 'move'])->name('bookings.move');

Route::post('/angebote/{offer}/wuensche', [WishSubmissionController::class, 'store'])->name('wishes.store');
Route::post('/vorschlaege/{assignment}/annehmen', [WishSubmissionController::class, 'accept'])->name('wishes.accept');
Route::post('/vorschlaege/{assignment}/ablehnen', [WishSubmissionController::class, 'decline'])->name('wishes.decline');

// Organisatoren: nur eigene Angebote (Policies in den Controllern)
Route::prefix('verwalten')->name('manage.')->group(function () {
    Route::get('angebote', [OfferPageController::class, 'index'])->name('offers.index');
    Route::get('angebote/neu', [OfferPageController::class, 'create'])->name('offers.create');
    Route::get('angebote/{offer}', [OfferPageController::class, 'edit'])->name('offers.edit');
    Route::post('angebote', [OfferController::class, 'store'])->name('offers.store');
    Route::put('angebote/{offer}', [OfferController::class, 'update'])->name('offers.update');
    Route::post('angebote/{offer}/veroeffentlichen', [OfferController::class, 'publish'])->name('offers.publish');
    Route::post('angebote/{offer}/absagen', [OfferController::class, 'cancel'])->name('offers.cancel');

    Route::post('angebote/{offer}/termine', [SlotController::class, 'store'])->name('slots.store');
    Route::post('angebote/{offer}/zeitraster', [SlotController::class, 'grid'])->name('slots.grid');
    Route::post('termine/{slot}/kopieren', [SlotController::class, 'copy'])->name('slots.copy');
    Route::delete('termine/{slot}', [SlotController::class, 'destroy'])->name('slots.destroy');

    Route::get('teilnehmer', [ParticipantController::class, 'index'])->name('participants.index');
    Route::get('wuensche', [WishController::class, 'index'])->name('wishes.index');
    Route::post('wuensche/{offer}/berechnen', [WishController::class, 'calculate'])->name('wishes.calculate');
    Route::post('wuensche/{offer}/freigeben', [WishController::class, 'release'])->name('wishes.release');
    Route::put('wuensche/zuteilungen/{assignment}', [WishController::class, 'update'])->name('wishes.assignment');

    Route::delete('buchungen/{booking}', [BookingController::class, 'remove'])->name('bookings.remove');
});

Route::get('/verwaltung', [AdminController::class, 'index'])->name('admin.index');
