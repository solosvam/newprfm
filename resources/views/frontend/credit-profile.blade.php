@extends('frontend.layout')
@section('page-styles')
<link rel="stylesheet" href="{{ asset('frontend/css/credit-profile.css?v=' . filemtime(public_path('frontend/css/credit-profile.css'))) }}">
@endsection
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Hissəli ödəniş məlumatları'])
<div class="cabinet__personal"><h2>Hissəli ödəniş məlumatları</h2>
<p>Bu məlumatları növbəti müraciətlərdə təkrar daxil etməyəcəksiniz.</p>
<form id="creditProfileForm" class="form credit-form" method="POST" action="{{ route('profile.credit.update') }}" enctype="multipart/form-data">
@csrf<div class="credit-grid">
<div class="credit-field"><label for="father_name">Ata adı *</label><input id="father_name" name="father_name" type="text"  value="{{ old('father_name', $profile?->father_name) }}" required><div class="invalid-feedback" data-error="father_name"></div></div>
<div class="credit-field"><label for="fin">FİN *</label><input id="fin" name="fin" type="text"  value="{{ old('fin', $profile?->fin) }}" required><div class="invalid-feedback" data-error="fin"></div></div>
<div class="credit-field"><label for="relative_1_name">1-ci qohumun adı *</label><input id="relative_1_name" name="relative_1_name" type="text"  value="{{ old('relative_1_name', $profile?->relative_1_name) }}" required><div class="invalid-feedback" data-error="relative_1_name"></div></div>
<div class="credit-field"><label for="relative_1_phone">1-ci qohumun nömrəsi *</label><input id="relative_1_phone" name="relative_1_phone" type="tel"  value="{{ old('relative_1_phone', $profile?->relative_1_phone) }}" required><div class="invalid-feedback" data-error="relative_1_phone"></div></div>
<div class="credit-field"><label for="relative_2_name">2-ci qohumun adı *</label><input id="relative_2_name" name="relative_2_name" type="text"  value="{{ old('relative_2_name', $profile?->relative_2_name) }}" required><div class="invalid-feedback" data-error="relative_2_name"></div></div>
<div class="credit-field"><label for="relative_2_phone">2-ci qohumun nömrəsi *</label><input id="relative_2_phone" name="relative_2_phone" type="tel"  value="{{ old('relative_2_phone', $profile?->relative_2_phone) }}" required><div class="invalid-feedback" data-error="relative_2_phone"></div></div>
<div class="credit-work-row">
<div class="credit-field"><label for="workplace_name">İş yerinin adı *</label><input id="workplace_name" name="workplace_name" type="text"  value="{{ old('workplace_name', $profile?->workplace_name) }}" required><div class="invalid-feedback" data-error="workplace_name"></div></div>
<div class="credit-field"><label for="salary">Əmək haqqı (AZN) *</label><input id="salary" name="salary" type="number" step="0.01" min="0.01" value="{{ old('salary', $profile?->salary) }}" required><div class="invalid-feedback" data-error="salary"></div></div>
<div class="credit-field"><label for="position">Vəzifə *</label><input id="position" name="position" type="text" value="{{ old('position', $profile?->position) }}" required><div class="invalid-feedback" data-error="position"></div></div>
</div>
<div class="credit-photos-row">
@foreach(['id_card_front'=>'Şəxsiyyət vəsiqəsinin ön şəkli','id_card_back'=>'Şəxsiyyət vəsiqəsinin arxa şəkli'] as $field=>$label)
@php $side = $field === 'id_card_front' ? 'front' : 'back'; $hasImage = (bool) $profile?->{$field}; @endphp
<div class="credit-field">
<label for="{{ $field }}">{{ $label }} *</label>
<div class="credit-photo" data-photo="{{ $field }}">
<img class="credit-preview" src="{{ $hasImage ? route('profile.credit.image', ['side' => $side]) : '' }}" alt="{{ $label }}" @if(!$hasImage) hidden @endif>
<span class="credit-empty" @if($hasImage) hidden @endif>Şəkil yüklənməyib</span>
<button type="button" class="credit-change">{{ $hasImage ? 'Dəyişdir' : 'Şəkil seç' }}</button>
<input class="credit-file" id="{{ $field }}" name="{{ $field }}" type="file" accept="image/jpeg,image/png,image/webp" @if(!$hasImage) required @endif>
<div class="invalid-feedback" data-error="{{ $field }}"></div>
</div>
</div>
@endforeach
</div>
</div><div class="form__submit"><button type="submit" id="creditSubmit">Yadda saxla</button></div></form></div>
</div></div></main>
@endsection
@section('page-scripts')
<script>
$(function(){
 const form=$('#creditProfileForm');
 form.find('.credit-change').on('click',function(){
   const input=$(this).closest('.credit-photo').find('input[type=file]');
   input.addClass('is-visible').trigger('click');
 });
 form.find('.credit-file').on('change',function(){
   const file=this.files?.[0];if(!file)return;
   const card=$(this).closest('.credit-photo');
   const preview=card.find('.credit-preview');
   const previous=preview.data('objectUrl');
   if(previous)URL.revokeObjectURL(previous);
   const objectUrl=URL.createObjectURL(file);
   preview.attr('src',objectUrl).data('objectUrl',objectUrl).prop('hidden',false);
   card.find('.credit-empty').prop('hidden',true);
   card.find('.credit-change').text('Dəyişdir');
 });
 form.on('submit',function(e){
  e.preventDefault();form.find('.is-invalid').removeClass('is-invalid');form.find('.invalid-feedback').text('');
  const btn=$('#creditSubmit').prop('disabled',true);
  $.ajax({url:form.attr('action'),type:'POST',data:new FormData(this),processData:false,contentType:false,
   success:function(r){
     $.each(r.images||{},function(field,url){
       if(!url)return;
       const card=form.find('[data-photo="'+field+'"]');
       const preview=card.find('.credit-preview');
       const previous=preview.data('objectUrl');
       if(previous){URL.revokeObjectURL(previous);preview.removeData('objectUrl');}
       preview.attr('src',url+'?v='+Date.now()).prop('hidden',false);
       card.find('.credit-empty').prop('hidden',true);
       card.find('.credit-change').text('Dəyişdir');
       card.find('.credit-file').val('').prop('required',false).removeClass('is-visible');
     });
     $.notify(r.message,'success');
   },
   error:function(xhr){
    if(xhr.status===422&&xhr.responseJSON?.errors){
     const errors=xhr.responseJSON.errors;
     $.each(errors,function(field,messages){
      form.find('[name="'+field+'"]').addClass('is-invalid');
      form.find('[data-error="'+field+'"]').text(messages[0]);
     });
     form.find('.is-invalid').first().trigger('focus');
     $.notify(Object.values(errors)[0][0],'error');
    }else{$.notify('Xəta baş verdi. Yenidən cəhd edin.','error');}
   },complete:function(){btn.prop('disabled',false);}
  });
 });
});
</script>
@endsection
