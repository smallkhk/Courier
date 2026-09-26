<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PricingRule;
use App\Models\Shipment;
use App\Services\InvoiceService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $q = Business::withCount('memberships', 'shipments')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return view('ops.businesses.index', ['businesses' => $q->paginate(30)->withQueryString()]);
    }

    public function show(Business $business)
    {
        return view('ops.businesses.show', [
            'b' => $business->load('memberships.user', 'invoices'),
            'rules' => PricingRule::with('service')->where('business_id', $business->id)->get(),
            'shipments' => Shipment::where('business_id', $business->id)->with('service')->latest()->limit(20)->get(),
            'uninvoiced' => Shipment::where('business_id', $business->id)->where('payment_method', 'invoice')->whereNull('invoice_id')->whereNotIn('status', ['cancelled'])->count(),
        ]);
    }

    public function update(Request $request, Business $business)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,approved,suspended,rejected',
            'payment_terms' => 'required|in:prepaid,invoice',
            'invoice_due_days' => 'required|integer|min:0|max:90',
        ]);
        $before = $business->only(array_keys($data));
        if ($data['status'] === 'approved' && ! $business->approved_at) {
            $data['approved_at'] = now();
            $data['approved_by'] = $request->user()->id;
        }
        $business->update($data);
        Audit::log('business.updated', $business, ['before' => $before, 'after' => $request->only(array_keys($before))]);

        return back()->with('success', 'Business account updated.');
    }

    public function invoice(Request $request, Business $business, InvoiceService $invoices)
    {
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        $invoice = $invoices->generate($business, Carbon::parse($data['from']), Carbon::parse($data['to']));

        return back()->with('success', "Invoice {$invoice->number} issued.");
    }
}
