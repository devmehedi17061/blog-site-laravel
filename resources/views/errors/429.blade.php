@extends('errors.shell')

@section('code', '429')
@section('title', 'Too many requests')
@section('heading', 'Slow down a little')

@section('body')
  <p>Too many requests came from your connection in a short time. Wait a minute, then try again.</p>
@endsection

@section('actions')
  <a class="primary" href="javascript:history.back()">Go back</a><a href="{{ url('/') }}">Homepage</a>
@endsection
