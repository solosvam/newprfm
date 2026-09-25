@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>__('reviews_my_reviews')])
<div class="cabinet-reviews">
@if($reviews->isEmpty())<div class="cabinet-content-empty"><h2>{{ __('reviews_my_reviews') }}</h2><p>{{ __('reviews_you_haven_t_written_any_reviews_yet') }}</p></div>
@else @foreach($reviews as $review)<div class="cabinet-review"><div><strong>{{ $review->product?->name }}</strong><small>{{ $review->product?->brand?->name }}</small><div class="review-stars">{{ str_repeat('★',(int)$review->rating) }}{{ str_repeat('☆',5-(int)$review->rating) }}</div></div><p>{{ $review->comment }}</p></div>@endforeach @endif
</div></div></div></main>
@endsection