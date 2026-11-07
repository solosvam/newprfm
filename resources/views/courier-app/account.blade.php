{{-- Kuryer → hesab: qalıq, haqq-hesab tarixçəsi, çıxış --}}
@extends('courier-app.layout', ['tab' => 'account'])
@section('title', 'Hesab')

@section('content')
    @include('courier-app._money', ['balance' => $balance, 'self' => true])

    <h2 class="section">Haqq-hesab tarixçəsi</h2>
    @include('courier-app._history', ['history' => $history, 'self' => true])

    <a href="{{ route('admin.logout') }}" class="btn btn--ghost btn--block logout" data-turbo="false">@include('courier-app._icon', ['name' => 'logout']) Çıxış</a>
@endsection
