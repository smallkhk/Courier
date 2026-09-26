@extends('layouts.public')
@section('title', 'Sandbox checkout')
@section('content')
<div class="mx-auto max-w-md">
    <div class="card overflow-hidden">
        <div class="bg-accent-300 px-6 py-3 text-center text-sm font-bold tracking-wide text-ink-900 uppercase">Sandbox payment — development only</div>
        <div class="card-body space-y-4">
            <p class="text-sm text-ink-700">This page simulates a payment provider so the booking and verification flow can be tested. <strong>No money moves.</strong> It is disabled in production.</p>
            <dl class="rounded-lg bg-ink-50 p-4 text-sm">
                <div class="flex justify-between"><dt>Reference</dt><dd class="font-mono">{{ $payment->reference }}</dd></div>
                <div class="flex justify-between"><dt>Amount</dt><dd class="font-semibold"><x-money :amount="$payment->amount" :currency="$payment->currency" /></dd></div>
            </dl>
            <form method="post" action="{{ URL::temporarySignedRoute('sandbox.complete', now()->addHour(), ['reference' => $payment->reference]) }}" class="grid gap-2">
                @csrf
                <button name="outcome" value="success" class="btn btn-primary" type="submit">Simulate successful payment</button>
                <button name="outcome" value="failed" class="btn btn-danger" type="submit">Simulate declined payment</button>
                <button name="outcome" value="abandoned" class="btn btn-secondary" type="submit">Simulate abandoned checkout</button>
            </form>
        </div>
    </div>
</div>
@endsection
