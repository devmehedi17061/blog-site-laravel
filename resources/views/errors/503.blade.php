@extends('errors.shell')

@section('code', '503')
@section('title', 'Back soon')
@section('heading', 'Back in a few minutes')

@section('body')
  <p>The site is being updated right now. Please try again shortly.</p>
@endsection

@section('actions')
  <button type="button" class="primary" onclick="location.reload()">Try again</button>
@endsection
