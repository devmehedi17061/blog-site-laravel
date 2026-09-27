@extends('errors.shell')

@section('code', '419')
@section('title', 'Page expired')
@section('heading', 'This page timed out')

@section('body')
  <p>For your security, forms stop working after a while. Your details were not sent.</p><p>Go back, refresh the page, and send it again.</p>
@endsection

@section('actions')
  <a class="primary" href="javascript:history.back()">Go back and try again</a><a href="{{ url('/') }}">Homepage</a>
@endsection
