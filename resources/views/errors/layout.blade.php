@extends('layouts.plain')

@section('title', $title)

@section('content')
    <div class="card">
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <p><a href="{{ url('/') }}">Zur Startseite</a></p>
    </div>
@endsection
