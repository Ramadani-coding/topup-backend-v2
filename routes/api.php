<?php

use App\Http\Controllers\DigiflazzController;
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

Route::post('/products/prepaid', [DigiflazzController::class, 'GetProductsPrepaid']);
Route::get('/brands', [DigiflazzController::class, 'getBrands']);
Route::get('/brand/{brand}', [DigiflazzController::class, 'getByBrand']);