<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});



Route::post('/payment/initiate', [PaymentController::class, 'initiatePayment'])->name('payment.initiate');
Route::post('/payment/notify', [PaymentController::class, 'notify'])->name('payment.notify');
Route::get('/payment/return', [PaymentController::class, 'return'])->name('payment.return');
