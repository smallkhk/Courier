@extends('layouts.public')
@section('title', 'Help & FAQs')
@section('hero')
<x-photo-hero image="courier-boxes" eyebrow="Help centre" icon="life-buoy" title="How can we help?" subtitle="Quick answers to common questions about sending, tracking and paying." />
@endsection
@section('content')
<div class="mx-auto max-w-3xl">
    @forelse($faqs as $f)
        <details class="card group mb-3 transition hover:border-brand-300" data-reveal>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 font-semibold text-ink-900">
                <span class="flex items-center gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon name="info" class="size-4" /></span>{{ $f->question }}</span>
                <x-icon name="chevron-down" class="size-5 text-ink-500 transition group-open:rotate-180" />
            </summary>
            <div class="px-5 pb-5 text-ink-700">{!! nl2br(e($f->answer)) !!}</div>
        </details>
    @empty
        <div class="card"><x-empty icon="info" title="No FAQs yet" /></div>
    @endforelse
    <div class="card card-body mt-8 flex flex-wrap items-center justify-between gap-4">
        <p class="font-medium">Still need help?</p>
        <a href="{{ route('support.contact') }}" class="btn btn-primary">Contact support</a>
    </div>
</div>
@endsection
