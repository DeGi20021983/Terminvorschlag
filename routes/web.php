<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\SlotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return view('home', ['user' => $request->user()]);
})->name('home');

// Mitarbeitende
Route::post('/angebote/{offer}/termine/{slot}/buchen', [BookingController::class, 'store'])->name('bookings.store');
Route::post('/buchungen/{booking}/stornieren', [BookingController::class, 'cancel'])->name('bookings.cancel');
Route::post('/buchungen/{booking}/umbuchen', [BookingController::class, 'move'])->name('bookings.move');

// Organisatoren: nur eigene Angebote (Policies in den Controllern)
Route::prefix('verwalten')->name('manage.')->group(function () {
    Route::post('angebote', [OfferController::class, 'store'])->name('offers.store');
    Route::put('angebote/{offer}', [OfferController::class, 'update'])->name('offers.update');
    Route::post('angebote/{offer}/veroeffentlichen', [OfferController::class, 'publish'])->name('offers.publish');
    Route::post('angebote/{offer}/absagen', [OfferController::class, 'cancel'])->name('offers.cancel');

    Route::post('angebote/{offer}/termine', [SlotController::class, 'store'])->name('slots.store');
    Route::post('angebote/{offer}/zeitraster', [SlotController::class, 'grid'])->name('slots.grid');
    Route::post('termine/{slot}/kopieren', [SlotController::class, 'copy'])->name('slots.copy');
    Route::delete('termine/{slot}', [SlotController::class, 'destroy'])->name('slots.destroy');

    Route::delete('buchungen/{booking}', [BookingController::class, 'remove'])->name('bookings.remove');
});
