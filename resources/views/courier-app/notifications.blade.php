{{-- Kuryer → bildirişlər: yenilər yuxarıda; basanda aid olduğu yer açılır və bildiriş oxunmuş olur --}}
@extends('courier-app.layout', ['tab' => 'notifications', 'refresh' => true])
@section('title', 'Bildirişlər')
@section('back', route('kuryer.home'))

@section('content')
    @if($notifications->whereNull('read_at')->isNotEmpty())
        <form method="POST" action="{{ route('kuryer.notifications.read-all') }}" class="notices__all" data-once>
            @csrf
            <button class="btn btn--ghost">Hamısını oxunmuş et</button>
        </form>
    @endif

    @forelse($notifications as $notice)
        <a href="{{ route('kuryer.notifications.open', $notice) }}" class="card notice {{ $notice->read_at ? '' : 'is-unread' }}">
            <span class="notice__dot" aria-hidden="true"></span>
            <span class="notice__main">
                <span class="notice__title">{{ $notice->title }}</span>
                @if($notice->body)<span class="notice__body">{{ $notice->body }}</span>@endif
                <span class="notice__time">{{ $notice->created_at->isToday() ? 'bu gün '.$notice->created_at->format('H:i') : $notice->created_at->format('d.m.Y H:i') }}</span>
            </span>
            <span class="order__go">@include('courier-app._icon', ['name' => 'chevron'])</span>
        </a>
    @empty
        <div class="empty">Bildiriş yoxdur.</div>
    @endforelse
@endsection
