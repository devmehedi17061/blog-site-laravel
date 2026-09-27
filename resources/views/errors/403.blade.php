@extends('errors.shell')

@section('code', '403')
@section('title', 'Not allowed')
@section('heading', 'You do not have access to this')

@section('body')
  <p>Your account cannot open this page. If you think it should, ask a site administrator.</p>
@endsection

@section('actions')
  <a class="primary" href="{{ url('/') }}">Go to the homepage</a><a href="javascript:history.back()">Go back</a>
@endsection
