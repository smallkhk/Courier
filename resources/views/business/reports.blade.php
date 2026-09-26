@extends('layouts.portal', ['portal' => 'business'])
@section('title', 'Reports')
@section('content')
<x-page-header title="Reports"><a href="{{ route('business.shipments.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" />Export shipments CSV</a></x-page-header>
@include('shared.report-body', ['summary' => $summary, 'from' => $from, 'to' => $to])
@endsection
