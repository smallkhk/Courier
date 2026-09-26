@extends('layouts.public')
@section('title', 'Help & FAQs')
@section('content')
<div class="mx-auto max-w-3xl">
    <x-page-header title="Help & FAQs" subtitle="Quick answers to common questions." />
    @forelse($faqs as $f)
        <details class="card mb-3 group">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 font-semibold text-ink-900">
                {{ $f->question }}
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
