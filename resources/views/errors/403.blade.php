@extends('errors.layout')
@section('code', '403')
@section('title', 'Access denied')
@section('heading', 'This area is off our route for you')
@section('message')
{{ ($exception ?? null)?->getMessage() ?: "Your account doesn't have permission to open this page. If you think it should, contact your administrator." }}
@endsection
