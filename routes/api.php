<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
| JSON API (see docs/API.md). Authenticated routes use the session cookie + X-CSRF-TOKEN
| header (same-origin browser clients). Errors are always JSON:
|   { "message": "...", "errors": { "field": ["..."] } }   with 401/403/404/422/429 status codes.
| Shipments are addressed by tracking number (never by sequential database ID).
*/

Route::post('/webhooks/{provider}', [Api\WebhookController::class, 'handle'])->whereIn('provider', ['stripe', 'paystack'])->name('webhooks');

Route::middleware('throttle:api')->group(function () {
    Route::get('/track/{tracking}', [Api\TrackingController::class, 'show'])->middleware('throttle:tracking')->name('track');
    Route::post('/quotes', [Api\QuoteController::class, 'store'])->middleware('throttle:quote')->name('quotes.store');

    Route::middleware('auth')->group(function () {
        Route::get('/auth/me', [Api\AuthController::class, 'me'])->name('auth.me');

        Route::get('/shipments', [Api\ShipmentController::class, 'index'])->name('shipments.index');
        Route::post('/shipments', [Api\ShipmentController::class, 'store'])->middleware('throttle:booking')->name('shipments.store');
        Route::get('/shipments/{shipment}', [Api\ShipmentController::class, 'show'])->name('shipments.show');
        Route::post('/shipments/{shipment}/cancel', [Api\ShipmentController::class, 'cancel'])->name('shipments.cancel');
        Route::post('/shipments/{shipment}/payment', [Api\PaymentController::class, 'store'])->name('shipments.payment');
        Route::get('/payments/{payment:reference}', [Api\PaymentController::class, 'show'])->name('payments.show');

        Route::middleware('role:dispatcher,admin,rider')->group(function () {
            Route::post('/shipments/{shipment}/events', [Api\OperationsController::class, 'event'])->name('shipments.events');
            Route::post('/shipments/{shipment}/delivery-attempts', [Api\OperationsController::class, 'attempt'])->name('shipments.attempts');
            Route::post('/shipments/{shipment}/proof-of-delivery', [Api\OperationsController::class, 'proof'])->name('shipments.proof');
        });
        Route::post('/shipments/{shipment}/assign-rider', [Api\OperationsController::class, 'assign'])->middleware('role:dispatcher,admin')->name('shipments.assign');

        Route::middleware('role:rider')->prefix('rider')->name('rider.')->group(function () {
            Route::get('/assignments', [Api\RiderController::class, 'assignments'])->name('assignments');
            Route::post('/assignments/{assignment}/accept', [Api\RiderController::class, 'accept'])->name('assignments.accept');
            Route::post('/location', [Api\RiderController::class, 'location'])->middleware('throttle:rider-location')->name('location');
            Route::post('/availability', [Api\RiderController::class, 'availability'])->name('availability');
            Route::post('/location-sharing', [Api\RiderController::class, 'sharing'])->name('sharing');
        });

        Route::get('/notifications', [Api\SupportController::class, 'notifications'])->name('notifications');
        Route::get('/support/tickets', [Api\SupportController::class, 'index'])->name('support.index');
        Route::post('/support/tickets', [Api\SupportController::class, 'store'])->name('support.store');
        Route::post('/support/tickets/{ticket:reference}/messages', [Api\SupportController::class, 'message'])->name('support.messages');

        Route::middleware('role:dispatcher,admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/dashboard', [Api\AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/shipments', [Api\AdminController::class, 'shipments'])->name('shipments');
            Route::get('/riders', [Api\AdminController::class, 'riders'])->name('riders');
            Route::post('/riders', [Api\AdminController::class, 'createRider'])->middleware('role:admin')->name('riders.store');
            Route::get('/audit-logs', [Api\AdminController::class, 'audit'])->middleware('role:admin')->name('audit');
        });
    });
});
