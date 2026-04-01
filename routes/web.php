<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('spaces', \App\Http\Controllers\SpaceController::class);
    Route::post('rooms/{room}/join', [\App\Http\Controllers\RoomController::class, 'join'])->name('rooms.join');
    Route::post('rooms/leave', [\App\Http\Controllers\RoomController::class, 'leave'])->name('rooms.leave');
});

require __DIR__.'/auth.php';
