@extends('errors.layout')
@section('code', '419')
@section('title')
Page expired
@endsection
@section('heading')
Your session timed out
@endsection
@section('message')
For your security, this page expired. Go back, refresh the page and try again.
@endsection
@section('actions')
<a href="javascript:history.back()" class="btn btn-accent btn-lg">Go back</a><a href="/" class="btn btn-on-dark btn-lg">Home</a>
@endsection
