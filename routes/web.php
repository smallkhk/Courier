<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Business;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Ops;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Rider;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TrackingController;
use App\Support\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/services', [PublicController::class, 'services'])->name('services');
Route::get('/pricing', [PublicController::class, 'pricing'])->name('pricing');
Route::post('/pricing', [PublicController::class, 'estimate'])->middleware('throttle:quote')->name('pricing.estimate');
Route::get('/coverage', [PublicController::class, 'coverage'])->name('coverage');
Route::get('/branches', [PublicController::class, 'branches'])->name('branches');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/faq', [PublicController::class, 'faq'])->name('faq');
Route::get('/legal/{page}', [PublicController::class, 'legal'])->whereIn('page', ['terms', 'privacy', 'delivery-policy'])->name('legal');
Route::get('/for-business', [PublicController::class, 'business'])->name('business.landing');
Route::get('/health', HealthController::class)->name('health');
Route::get('/manifest.webmanifest', function () {
    $name = Settings::get('business_name');

    return response()->json([
        'name' => $name,
        'short_name' => mb_substr($name, 0, 12),
        'description' => 'Book, pay for and track parcel deliveries.',
        'start_url' => '/portal',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#081640',
        'theme_color' => '#0f2f8f',
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => '/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
        'shortcuts' => [['name' => 'Track a parcel', 'url' => '/track'], ['name' => 'Send a parcel', 'url' => '/send']],
    ])->header('Content-Type', 'application/manifest+json');
})->name('manifest');

Route::get('/contact', [SupportController::class, 'contact'])->name('support.contact');
Route::post('/contact', [SupportController::class, 'submit'])->middleware('throttle:contact')->name('support.contact.submit');

// Tracking (rate limited; responses never reveal private details)
Route::get('/track', [TrackingController::class, 'form'])->name('track.form');
Route::post('/track', [TrackingController::class, 'lookup'])->middleware('throttle:tracking')->name('track.lookup');
Route::get('/track/{tracking}', [TrackingController::class, 'show'])->middleware('throttle:tracking')->name('track.show');
Route::post('/track/{tracking}/verify', [TrackingController::class, 'verify'])->middleware('throttle:tracking-verify')->name('track.verify');

// Booking (guest booking allowed when enabled in settings)
Route::get('/send', [BookingController::class, 'start'])->name('book.start');
Route::post('/send/quote', [BookingController::class, 'quote'])->middleware('throttle:quote')->name('book.quote');
Route::get('/send/review', [BookingController::class, 'review'])->name('book.review');
Route::post('/send/confirm', [BookingController::class, 'confirm'])->middleware('throttle:booking')->name('book.confirm');
Route::get('/shipments/{shipment}/confirmation', [BookingController::class, 'confirmation'])->name('book.confirmation');

// Checkout & payment
Route::get('/shipments/{shipment}/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/shipments/{shipment}/pay', [CheckoutController::class, 'pay'])->middleware('throttle:booking')->name('checkout.pay');
Route::get('/payments/callback', [CheckoutController::class, 'callback'])->name('payments.callback');
Route::get('/payments/sandbox/{reference}', [CheckoutController::class, 'sandbox'])->middleware('signed')->name('sandbox.checkout');
Route::post('/payments/sandbox/{reference}', [CheckoutController::class, 'sandboxComplete'])->middleware('signed')->name('sandbox.complete');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'show'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [Auth\RegisterController::class, 'show'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store'])->middleware('throttle:contact');
    Route::get('/forgot-password', [Auth\PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Auth\PasswordController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [Auth\PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [Auth\VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Auth\VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Auth\VerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('/portal', [Auth\LoginController::class, 'portal'])->name('portal');

    // Protected delivery-evidence files (authorised per request, never public URLs)
    Route::get('/files/proofs/{proof}/{kind}', [FileController::class, 'proof'])->whereIn('kind', ['photo', 'signature'])->name('files.proof');
    Route::get('/files/attempts/{attempt}', [FileController::class, 'attempt'])->name('files.attempt');
});

/*
|--------------------------------------------------------------------------
| Customer portal
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:customer'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', [Account\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/shipments', [Account\ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('/shipments/{shipment}', [Account\ShipmentController::class, 'show'])->name('shipments.show');
    Route::post('/shipments/{shipment}/cancel', [Account\ShipmentController::class, 'cancel'])->name('shipments.cancel');
    Route::get('/addresses', [Account\AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [Account\AddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [Account\AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [Account\AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::get('/payments', [Account\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment:reference}', [Account\PaymentController::class, 'show'])->name('payments.show');
    Route::get('/notifications', [Account\ProfileController::class, 'notifications'])->name('notifications');
    Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Account\ProfileController::class, 'password'])->name('profile.password');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/new', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket:reference}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket:reference}/reply', [SupportController::class, 'reply'])->name('support.reply');
});

/*
|--------------------------------------------------------------------------
| Business portal
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:customer'])->prefix('business')->name('business.')->group(function () {
    Route::get('/register', [Business\RegistrationController::class, 'show'])->name('register');
    Route::post('/register', [Business\RegistrationController::class, 'store'])->name('register.store');

    Route::middleware('business')->group(function () {
        Route::get('/', [Business\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/shipments', [Business\ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/export', [Business\ShipmentController::class, 'export'])->name('shipments.export');
        Route::get('/shipments/{shipment}', [Business\ShipmentController::class, 'show'])->name('shipments.show');
        Route::post('/shipments/pay', [Business\ShipmentController::class, 'payMany'])->middleware('business:create_shipments')->name('shipments.pay');
    });
    Route::middleware('business:create_shipments')->group(function () {
        Route::get('/bulk', [Business\BulkController::class, 'create'])->name('bulk.create');
        Route::get('/bulk/template.csv', [Business\BulkController::class, 'template'])->name('bulk.template');
        Route::post('/bulk', [Business\BulkController::class, 'preview'])->name('bulk.preview');
        Route::get('/bulk/{import}', [Business\BulkController::class, 'show'])->name('bulk.show');
        Route::post('/bulk/{import}/confirm', [Business\BulkController::class, 'confirm'])->name('bulk.confirm');
        Route::get('/addresses', [Business\AddressController::class, 'index'])->name('addresses.index');
        Route::post('/addresses', [Business\AddressController::class, 'store'])->name('addresses.store');
        Route::delete('/addresses/{address}', [Business\AddressController::class, 'destroy'])->name('addresses.destroy');
    });
    Route::middleware('business:view_invoices')->group(function () {
        Route::get('/invoices', [Business\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice:number}', [Business\InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{invoice:number}/pay', [Business\InvoiceController::class, 'pay'])->name('invoices.pay');
    });
    Route::middleware('business:view_reports')->group(function () {
        Route::get('/reports', [Business\ReportController::class, 'index'])->name('reports');
    });
    Route::middleware('business:manage_team')->group(function () {
        Route::get('/team', [Business\TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [Business\TeamController::class, 'store'])->name('team.store');
        Route::put('/team/{member}', [Business\TeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{member}', [Business\TeamController::class, 'destroy'])->name('team.destroy');
    });
    Route::middleware('business:manage_company')->group(function () {
        Route::get('/profile', [Business\ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [Business\ProfileController::class, 'update'])->name('profile.update');
    });
});

/*
|--------------------------------------------------------------------------
| Rider portal (web only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:rider'])->prefix('rider')->name('rider.')->group(function () {
    Route::get('/', [Rider\RiderController::class, 'dashboard'])->name('dashboard');
    Route::get('/history', [Rider\RiderController::class, 'history'])->name('history');
    Route::post('/duty', [Rider\RiderController::class, 'duty'])->name('duty');
    Route::get('/jobs/{assignment}', [Rider\JobController::class, 'show'])->name('jobs.show');
    Route::post('/jobs/{assignment}/respond', [Rider\JobController::class, 'respond'])->name('jobs.respond');
    Route::post('/jobs/{assignment}/status', [Rider\JobController::class, 'status'])->name('jobs.status');
    Route::post('/jobs/{assignment}/proof', [Rider\JobController::class, 'proof'])->name('jobs.proof');
    Route::post('/jobs/{assignment}/failed', [Rider\JobController::class, 'failed'])->name('jobs.failed');
});

/*
|--------------------------------------------------------------------------
| Operations (dispatcher + admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:dispatcher,admin'])->prefix('ops')->name('ops.')->group(function () {
    Route::get('/', [Ops\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/shipments', [Ops\ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('/shipments/export', [Ops\ShipmentController::class, 'export'])->name('shipments.export');
    Route::get('/shipments/{shipment}', [Ops\ShipmentController::class, 'show'])->name('shipments.show');
    Route::post('/shipments/{shipment}/status', [Ops\ShipmentController::class, 'status'])->name('shipments.status');
    Route::post('/shipments/{shipment}/assign', [Ops\ShipmentController::class, 'assign'])->name('shipments.assign');
    Route::post('/shipments/{shipment}/note', [Ops\ShipmentController::class, 'note'])->name('shipments.note');
    Route::post('/shipments/{shipment}/proof', [Ops\ShipmentController::class, 'proof'])->name('shipments.proof');
    Route::post('/shipments/{shipment}/mark-paid', [Ops\ShipmentController::class, 'confirmOffline'])->middleware('role:admin')->name('shipments.confirm-offline');
    Route::get('/dispatch', [Ops\DispatchController::class, 'index'])->name('dispatch');
    Route::get('/map', [Ops\DispatchController::class, 'map'])->name('map');
    Route::get('/map/riders.json', [Ops\DispatchController::class, 'mapData'])->name('map.data');
    Route::get('/exceptions', [Ops\ExceptionController::class, 'index'])->name('exceptions');
    Route::post('/attempts/{attempt}/resolve', [Ops\ExceptionController::class, 'resolve'])->name('attempts.resolve');
    Route::get('/payments', [Ops\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment:reference}', [Ops\PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment:reference}/verify', [Ops\PaymentController::class, 'verify'])->name('payments.verify');
    Route::post('/payments/{payment:reference}/refund', [Ops\PaymentController::class, 'refund'])->middleware('role:admin')->name('payments.refund');
    Route::get('/cod', [Ops\CodController::class, 'index'])->name('cod.index');
    Route::put('/cod/{cod}', [Ops\CodController::class, 'update'])->name('cod.update');
    Route::get('/support', [Ops\SupportController::class, 'index'])->name('support.index');
    Route::get('/support/{ticket:reference}', [Ops\SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket:reference}/reply', [Ops\SupportController::class, 'reply'])->name('support.reply');
    Route::put('/support/{ticket:reference}', [Ops\SupportController::class, 'update'])->name('support.update');
    Route::get('/customers', [Ops\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{user}', [Ops\CustomerController::class, 'show'])->name('customers.show');
    Route::get('/businesses', [Ops\BusinessController::class, 'index'])->name('businesses.index');
    Route::get('/businesses/{business}', [Ops\BusinessController::class, 'show'])->name('businesses.show');
    Route::put('/businesses/{business}', [Ops\BusinessController::class, 'update'])->middleware('role:admin')->name('businesses.update');
    Route::post('/businesses/{business}/invoice', [Ops\BusinessController::class, 'invoice'])->middleware('role:admin')->name('businesses.invoice');
    Route::get('/riders', [Ops\RiderController::class, 'index'])->name('riders.index');
    Route::get('/riders/new', [Ops\RiderController::class, 'create'])->middleware('role:admin')->name('riders.create');
    Route::post('/riders', [Ops\RiderController::class, 'store'])->middleware('role:admin')->name('riders.store');
    Route::get('/riders/{user}', [Ops\RiderController::class, 'show'])->name('riders.show');
    Route::put('/riders/{user}', [Ops\RiderController::class, 'update'])->middleware('role:admin')->name('riders.update');
    Route::get('/reports', [Ops\ReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [Ops\ReportController::class, 'export'])->name('reports.export');
    Route::get('/notifications', [Ops\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{log}/retry', [Ops\NotificationController::class, 'retry'])->name('notifications.retry');
});

/*
|--------------------------------------------------------------------------
| Administration (admin only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/new', [Admin\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::get('/workflow', [Admin\SettingsController::class, 'workflow'])->name('workflow');
    Route::get('/audit', [Admin\AuditController::class, 'index'])->name('audit');
    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

    Route::get('/manage/{resource}', [Admin\ResourceController::class, 'index'])->name('resource.index');
    Route::get('/manage/{resource}/new', [Admin\ResourceController::class, 'create'])->name('resource.create');
    Route::post('/manage/{resource}', [Admin\ResourceController::class, 'store'])->name('resource.store');
    Route::get('/manage/{resource}/{id}', [Admin\ResourceController::class, 'edit'])->name('resource.edit');
    Route::put('/manage/{resource}/{id}', [Admin\ResourceController::class, 'update'])->name('resource.update');
    Route::delete('/manage/{resource}/{id}', [Admin\ResourceController::class, 'destroy'])->name('resource.destroy');
});
