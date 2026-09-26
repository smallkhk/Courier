@extends('layouts.portal', ['portal' => 'ops'])
@section('title', 'Status workflow')
@section('content')
<x-page-header title="Shipment status workflow" subtitle="Allowed transitions and who may perform them. Enforced on the server for every change." />
<x-alert type="info" class="mb-4">The state machine is defined in <code>app/Enums/ShipmentStatus.php</code> (see docs/STATUS_WORKFLOW.md) so changes are code-reviewed and tested. Customer-facing wording can be edited in message templates.</x-alert>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>From</th><th>Can move to (roles)</th></tr></thead>
    <tbody>@foreach($statuses as $st)<tr>
        <td class="whitespace-nowrap"><x-status-badge :status="$st" /></td>
        <td>@forelse($transitions[$st->value] ?? [] as $to => $roles)<span class="mr-3 inline-block py-0.5 text-sm"><strong>{{ \App\Enums\ShipmentStatus::from($to)->label() }}</strong> <span class="text-xs text-ink-500">({{ implode(', ', $roles) }})</span></span>@empty<span class="text-ink-500">Final status</span>@endforelse</td>
    </tr>@endforeach</tbody>
</table></div></div>
<p class="mt-4 text-sm text-ink-600">“Delivered” additionally requires a proof-of-delivery record (recipient name, plus signature/photo/code when required in settings). Riders can only update shipments currently assigned to them.</p>
@endsection
