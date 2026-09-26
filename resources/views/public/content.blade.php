@extends('layouts.public')
@section('title', $block->title ?? $fallbackTitle)
@if(request()->routeIs('about'))
@section('hero')
<x-photo-hero image="courier-boxes" eyebrow="About us" icon="users" :title="$block->title ?? $fallbackTitle" subtitle="The people and process behind every delivery." />
@endsection
@endif
@section('content')
<article class="mx-auto max-w-3xl">
    @unless(request()->routeIs('about'))<h1 class="text-3xl">{{ $block->title ?? $fallbackTitle }}</h1>@endunless
    @if($block)
        <p class="mt-2 text-sm text-ink-500">Last updated {{ $block->updated_at->format('j F Y') }}</p>
        <div class="prose-simple mt-8 text-ink-700">
            {{-- Content is escaped; blank lines become paragraphs and "## " lines become headings. --}}
            @foreach(preg_split('/\n\s*\n/', trim($block->body)) as $para)
                @if(str_starts_with($para, '## '))<h2>{{ substr($para, 3) }}</h2>
                @elseif(str_starts_with($para, '- '))<ul>@foreach(explode("\n", $para) as $li)<li>{{ ltrim($li, '- ') }}</li>@endforeach</ul>
                @else<p>{!! nl2br(e($para)) !!}</p>@endif
            @endforeach
        </div>
    @else
        <x-alert type="warning" class="mt-6">This page has not been written yet. An administrator can add it under Website content.</x-alert>
    @endif
</article>
@endsection
