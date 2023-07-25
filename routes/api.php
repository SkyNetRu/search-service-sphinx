<?php

use App\Http\Controllers\Api\Sphinx;
use App\Http\Controllers\Api\Heic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::get('search', [Sphinx::class, 'search']);
Route::post('convert-heic-2-jpg', [Heic::class, 'convertHeic2Jpg']);
