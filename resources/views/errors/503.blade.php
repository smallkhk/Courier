@extends('errors.layout')
@section('code', '503')
@section('title')
Down for maintenance
@endsection
@section('heading')
Back on the road shortly
@endsection
@section('message')
We're doing some scheduled maintenance to keep deliveries running smoothly. Please check back in a few minutes.
@endsection
@section('actions')
<a href="/" class="btn btn-accent btn-lg">Try again</a>
@endsection
