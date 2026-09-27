@extends('layouts.public')
@section('title', 'Payment')
@section('content')
<div class="mx-auto max-w-3xl">
    @include('booking._steps', ['current' => 3])
    <div class="grid gap-6 md:grid-cols-[1fr_320px]">
        <div class="card card-body">
            <h1 class="text-2xl">Complete payment</h1>
            <p class="mt-2 text-ink-600">Your shipment <span class="font-mono font-semibold text-ink-900">{{ $shipment->tracking_number }}</span> is reserved and will be confirmed as soon as payment is verified.</p>
            <div class="mt-6 rounded-lg bg-ink-50 p-4">
                <p class="text-sm text-ink-600">Amount due</p>
                <p class="text-3xl font-bold"><x-money :amount="$shipment->total" :currency="$shipment->currency" /></p>
            </div>
            <form method="post" action="{{ route('checkout.pay', $shipment) }}" class="mt-6">
                @csrf
                <button class="btn btn-primary btn-lg w-full" type="submit"><x-icon name="lock" class="size-4" />Pay securely</button>
            </form>
            <p class="mt-3 flex items-center gap-2 text-xs text-ink-500"><x-icon name="shield" class="size-4" />
                @if($provider === 'sandbox')SANDBOX MODE — you will see a test checkout; no real money moves.@else You'll be redirected to {{ $provider === 'stripe' ? 'Stripe' : 'Paystack' }}'s secure checkout{{ $provider === 'stripe' ? ' (card, Apple Pay, Google Pay)' : '' }}. We never see or store your card details.@endif
            </p>
            <p class="mt-3 text-xs text-ink-500">If you already paid and were interrupted, don't pay again — we check with the payment provider automatically. Clicking "Pay securely" again resumes the same payment.</p>
        </div>
        <aside class="space-y-4">
            <div class="card card-body text-sm">
                <h2 class="text-base">Summary</h2>
                <p class="mt-2">{{ $shipment->service->name }}</p>
                <p class="text-ink-600">{{ $shipment->pickup_city }} → {{ $shipment->delivery_city }}</p>
                <div class="mt-3"><x-price-breakdown :breakdown="$shipment->price_breakdown" :currency="$shipment->currency" /></div>
            </div>
            @if($payments->isNotEmpty())
                <div class="card card-body text-sm">
                    <h2 class="text-base">Payment attempts</h2>
                    <ul class="mt-2 space-y-2">
                        @foreach($payments as $p)
                            <li class="flex justify-between gap-2"><span class="font-mono text-xs">{{ $p->reference }}</span>
                                <x-pill :tone="['successful' => 'success', 'failed' => 'danger', 'cancelled' => 'neutral', 'pending' => 'warning', 'refunded' => 'info'][$p->status]">{{ ucfirst($p->status) }}</x-pill></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection
