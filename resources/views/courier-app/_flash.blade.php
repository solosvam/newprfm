{{-- Əməliyyatın nəticəsi və xətalar --}}
@if(session('success'))<div class="flash flash--ok" role="status">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="flash flash--warn" role="status">{{ session('warning') }}</div>@endif
@if($errors->any())
    <div class="flash flash--error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
@endif
