<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = User::where('role', 'customer')->withCount('shipments')->latest();
        if ($request->filled('q')) {
            $t = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t)->orWhere('phone', 'like', $t));
        }

        return view('ops.customers.index', ['users' => $q->paginate(30)->withQueryString()]);
    }

    public function show(User $user)
    {
        abort_unless($user->isRole('customer'), 404);
        Audit::log('ops.customer_viewed', $user); // Access to personal data is recorded.

        return view('ops.customers.show', [
            'u' => $user->load('addresses', 'businesses'),
            'shipments' => Shipment::where('user_id', $user->id)->with('service')->latest()->paginate(20),
        ]);
    }
}
