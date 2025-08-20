<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SupportTicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application.
| These routes are loaded by the RouteServiceProvider within the admin middleware.
|
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    // Tenants Management
    Route::resource('tenants', TenantController::class);
    
    // Tenant switching for super admins
    Route::post('/switch-tenant', function (Request $request) {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403);
        }
        
        session(['selected_tenant_id' => $request->tenant_id]);
        
        return back()->with('success', 'Tenant switched successfully');
    })->name('switch-tenant');

    // Users Management  
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/invite', [UserController::class, 'sendInvite'])->name('users.invite');
    
    // Support Tickets
    Route::resource('support-tickets', SupportTicketController::class);
    Route::post('/support-tickets/{ticket}/assign', [SupportTicketController::class, 'assign'])->name('support-tickets.assign');
    Route::post('/support-tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support-tickets.reply');
    
    // Landing Page CMS
    Route::prefix('landing-page')->name('landing-page.')->group(function () {
        Route::get('/', [LandingPageController::class, 'index'])->name('index');
        Route::post('/', [LandingPageController::class, 'update'])->name('update');
        Route::post('/media', [LandingPageController::class, 'uploadMedia'])->name('media.upload');
    });
});

// Public invitation acceptance routes
Route::get('/invite/{token}', [UserController::class, 'showInvite'])->name('invite.show');
Route::post('/invite/{token}', [UserController::class, 'acceptInvite'])->name('invite.accept');