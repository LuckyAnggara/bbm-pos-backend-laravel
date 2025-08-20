<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Admin test page
Route::get('/admin-test', function () {
    return view('admin-test');
});

// Include admin routes
require __DIR__.'/admin.php';

// Landing page routes
// // Route::get('/', [LandingController::class, 'index']);
// Route::get('/pricing', [LandingController::class, 'pricing']);
// Route::get('/features', [LandingController::class, 'features']);
// Route::post('/contact', [LandingController::class, 'contact']);
