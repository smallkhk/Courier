{{-- Customer/business view of a shipment: $shipment, optional $actions slot content via @section --}}
@php $tz = \App\Support\Settings::get('timezone'); @endphp
<div class="grid gap-6 lg:grid-cols-[1fr_340px]">
    <div class="space-y-6">
        <div class="card card-body grid gap-6 sm:grid-cols-2">
            <div>
                <h2 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">From</h2>
                <p class="mt-1 font-medium">{{ $shipment->sender_name }}</p>
                <p class="text-sm text-ink-700">{{ $shipment->addressLine('pickup') }}</p>
                <p class="text-sm text-ink-600">{{ \App\Support\Phone::display($shipment->sender_phone) }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold tracking-wide text-ink-500 uppercase">To</h2>
                <p class="mt-1 font-medium">{{ $shipment->recipient_name }}</p>
                <p class="text-sm text-ink-700">{{ $shipment->addressLine('delivery') }}</p>
                <p class="text-sm text-ink-600">{{ \App\Support\Phone::display($shipment->recipient_phone) }}</p>
            </div>
        </div>
        <section class="card card-body" aria-labelledby="tl">
            <h2 id="tl" class="mb-6 text-lg">Tracking history</h2>
            <x-timeline :events="$shipment->events" />
        </section>
        @if($shipment->proofs->isNotEmpty())
            <section class="card card-body">
                <h2 class="text-lg">Proof of delivery</h2>
                @foreach($shipment->proofs as $p)
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-ink-500">Received by</dt><dd class="font-medium">{{ $p->recipient_name }}</dd></div>
                        <div><dt class="text-ink-500">Delivered</dt><dd>{{ $p->delivered_at->timezone($tz)->format('D j M Y, g:i a') }}</dd></div>
                        @if($p->code_verified)<div><dt class="text-ink-500">Delivery code</dt><dd><x-pill tone="success" icon="check">Verified</x-pill></dd></div>@endif
                    </dl>
                    <div class="mt-3 flex flex-wrap gap-4">
                        @if($p->signature_path)<figure><img src="{{ route('files.proof', [$p, 'signature']) }}" alt="Recipient signature" class="h-24 rounded border border-ink-200 bg-white"><figcaption class="text-xs text-ink-500">Signature</figcaption></figure>@endif
                        @if($p->photo_path)<figure><img src="{{ route('files.proof', [$p, 'photo']) }}" alt="Delivery photo" class="h-24 rounded border border-ink-200 object-cover"><figcaption class="text-xs text-ink-500">Photo</figcaption></figure>@endif
                    </div>
                @endforeach
            </section>
        @endif
    </div>
    <aside class="space-y-4">
        <div class="card card-body text-sm">
            <h2 class="text-base">Shipment</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between gap-2"><dt class="text-ink-500">Service</dt><dd>{{ $shipment->service->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink-500">Contents</dt><dd class="text-right">{{ $shipment->package_description }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-ink-500">Parcels</dt><dd>{{ $shipment->parcel_count }} · {{ \App\Support\Units::weight($shipment->chargeable_weight_kg) }}</dd></div>
                @if($shipment->customs)<div class="flex justify-between gap-2"><dt class="text-ink-500">Customs</dt><dd class="text-right">{{ \App\Models\Shipment::CUSTOMS_CONTENTS[$shipment->customs['contents_type']] ?? '' }}<br><span class="text-xs text-ink-500">{{ $shipment->customs['description'] }}</span></dd></div>@endif
                @if($shipment->estimated_delivery_to && ! $shipment->status->isTerminal())<div class="flex justify-between gap-2"><dt class="text-ink-500">Estimated</dt><dd>{{ $shipment->estimated_delivery_to->format('D j M') }} <span class="text-xs text-ink-500">(estimate)</span></dd></div>@endif
                <div class="flex justify-between gap-2"><dt class="text-ink-500">Payment</dt><dd class="uppercase">{{ $shipment->payment_method }}</dd></div>
            </dl>
            <div class="mt-4 border-t border-ink-200 pt-3"><x-price-breakdown :breakdown="$shipment->price_breakdown" :currency="$shipment->currency" /></div>
        </div>
        {{ $slot ?? '' }}
    </aside>
</div>
