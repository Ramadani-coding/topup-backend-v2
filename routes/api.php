<?php

use App\Http\Controllers\DigiflazzController;
use App\Http\Controllers\MidtransController;
use App\Services\DigiflazzService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/get/prepaid', [DigiflazzController::class, 'GetProductsPrepaid']);
Route::get('/brands', [DigiflazzController::class, 'getBrands']);
Route::get('/buy/{brand}', [DigiflazzController::class, 'getByBrand']);
Route::post('/buy/{brand}/topup', [DigiflazzController::class, 'createOrder'])->where('brand', '[A-Za-z0-9\-]+');;

Route::post('/midtrans/webhook', [MidtransController::class, 'handleWebhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
