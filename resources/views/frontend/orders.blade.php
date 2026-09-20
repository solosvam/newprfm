@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Sifarişlərimin tarixçəsi'])
<div class="cabinet-content-empty"><h2>Sifarişlərimin tarixçəsi</h2><p>Hələ sifarişiniz yoxdur.</p></div>
</div></div></main>
@endsection