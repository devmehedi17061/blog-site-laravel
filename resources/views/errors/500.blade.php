@extends('errors.shell')

@section('code', '500')
@section('title', 'Something went wrong')
@section('heading', 'Something went wrong on our side')

@section('body')
  <p>It is not something you did. The problem has been logged and we will look into it.</p><p>Try again in a few minutes.</p>
@endsection

@section('actions')
  <a class="primary" href="{{ url('/') }}">Go to the homepage</a><button type="button" onclick="location.reload()">Try again</button>
@endsection
