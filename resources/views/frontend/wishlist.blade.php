@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Bəyəndiyim ətirlər'])
<div class="cabinet-content-empty"><h2>Bəyəndiyim ətirlər</h2><p>Hələ bəyəndiyiniz ətir yoxdur.</p></div>
</div></div></main>
@endsection