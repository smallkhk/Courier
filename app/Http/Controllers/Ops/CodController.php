<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Cash-on-delivery reconciliation:
 *  pending → collected (rider records cash at delivery) → remitted (rider hands cash to branch)
 *  → reconciled (finance confirms the amount) | discrepancy (amount differs; investigate).
 */
class CodController extends Controller
{
    public function index(Request $request)
    {
        return view('ops.cod', [
            'rows' => CodCollection::with('shipment', 'rider')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function update(Request $request, CodCollection $cod)
    {
        $data = $request->validate(['action' => 'required|in:remitted,reconciled,discrepancy', 'note' => 'nullable|string|max:250']);
        $allowed = ['remitted' => ['collected', 'discrepancy'], 'reconciled' => ['remitted', 'discrepancy'], 'discrepancy' => ['collected', 'remitted']];
        abort_unless(in_array($cod->status, $allowed[$data['action']], true), 422, 'Not allowed from the current COD status.');
        $cod->forceFill(array_filter([
            'status' => $data['action'],
            'remitted_at' => $data['action'] === 'remitted' ? now() : null,
            'reconciled_by' => $data['action'] === 'reconciled' ? $request->user()->id : null,
            'reconciled_at' => $data['action'] === 'reconciled' ? now() : null,
            'note' => $data['note'] ?? null,
        ], fn ($v) => $v !== null))->save();
        Audit::log('cod.'.$data['action'], $cod->shipment, ['cod_id' => $cod->id, 'note' => $data['note'] ?? null]);

        return back()->with('success', 'Cash record updated.');
    }
}
