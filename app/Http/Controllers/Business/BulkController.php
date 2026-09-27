<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\BulkImport;
use App\Models\Service;
use App\Services\BulkImportService;
use Illuminate\Http\Request;

class BulkController extends Controller
{
    public function create(Request $request)
    {
        $m = $request->attributes->get('membership');

        return view('business.bulk.create', [
            'addresses' => Address::where('business_id', $m->business_id)->get(),
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
            'recent' => BulkImport::where('business_id', $m->business_id)->latest()->limit(5)->get(),
        ]);
    }

    public function template(BulkImportService $bulk)
    {
        return response($bulk->templateCsv(), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="shipments-template.csv"']);
    }

    public function preview(Request $request, BulkImportService $bulk)
    {
        $m = $request->attributes->get('membership');
        $request->validate([
            'file' => 'required|file|max:2048|mimes:csv,txt',
            'pickup_address_id' => 'required|integer',
        ]);
        $pickup = Address::where('business_id', $m->business_id)->findOrFail($request->integer('pickup_address_id'));
        $import = $bulk->preview($request->file('file'), $m->business, $request->user(), $pickup);

        return redirect()->route('business.bulk.show', $import->id);
    }

    public function show(Request $request, string $import)
    {
        $m = $request->attributes->get('membership');

        return view('business.bulk.show', ['import' => BulkImport::where('business_id', $m->business_id)->findOrFail($import), 'business' => $m->business]);
    }

    public function confirm(Request $request, string $import, BulkImportService $bulk)
    {
        $m = $request->attributes->get('membership');
        $imp = BulkImport::where('business_id', $m->business_id)->findOrFail($import);
        $created = $bulk->confirm($imp, $request->user(), $m->business, $request->boolean('include_warnings'));

        return redirect()->route('business.shipments.index', $m->business->billsByInvoice() ? [] : ['status' => 'pending_payment'])
            ->with('success', count($created).' shipment(s) created.'.($m->business->billsByInvoice() ? ' They will appear on your next invoice.' : ' Select them below to pay in one checkout.'));
    }
}
