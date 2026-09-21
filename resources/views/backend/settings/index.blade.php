@php $title='Ayarlar'; @endphp
@extends('backend.layout',['title'=>$title])
@section('content')
<div class="container">
 <div class="page-title-container"><h1 class="mb-0 pb-0 display-4">Ayarlar</h1></div>
 <div class="card"><div class="card-body">
  <form method="POST" action="{{ route('admin.settings.update') }}">@csrf
   <div class="mb-4">
    <label class="form-label">Sifariş bonusu (%)</label>
    <input type="number" step="0.01" min="0" max="100" name="order_bonus_percent" value="{{ old('order_bonus_percent',$bonusPercent) }}" class="form-control" required>
    <div class="form-text">Müştərinin sifariş məbləğinin neçə faizini bonus kimi qazanacağını müəyyən edir.</div>
    @error('order_bonus_percent')<div class="text-danger mt-1">{{ $message }}</div>@enderror
   </div>
   <button class="btn btn-primary" type="submit">Yadda saxla</button>
  </form>
 </div></div>
</div>
@endsection