{{--
  Kuryer → sifariş. Depolardan götürmə ("X AZN ödədim, götürdüm" / "Ödənişsiz götürdüm" / Problem) — yalnız yola çıxmazdan əvvəl,
  başqa kuryerə ötürmə, müştəri və ünvan, çatdırılma (Çatdırılmaya başladım → Təhvil verdim),
  qapıda imtina və imtina edilən məhsulun depoya qaytarılması ("Qaytardım").
--}}
@use('App\Models\Procurement\OrderItemAllocation', 'Part')
@php
    $code = $order->status?->code;
    $money = fn ($cents) => number_format($cents / 100, 2).' AZN';
    $steps = ['courier_assigned' => 'Toplama', 'sent' => 'Yoldayam', 'delivered' => 'Təhvil verildi'];
    $reached = array_search($code, array_keys($steps), true);
    $a = $order->address;
    $addressLine = collect([$a?->city, $a?->address])->filter()->implode(', ');
    $addressExtra = collect([
        $a?->building ? 'Bina '.$a->building : null, $a?->entrance ? 'Blok '.$a->entrance : null,
        $a?->floor ? 'Mərtəbə '.$a->floor : null, $a?->apartment ? 'Mənzil '.$a->apartment : null,
    ])->filter()->implode(' · ');
    $phone = $order->customer?->mobile;
    $phone2 = $order->customer?->mobile_2; // ehtiyat nömrə
    $cancelled = $code === 'cancelled';
    $variant = fn ($item) => collect([$item->product?->brand?->name, $item->variant?->size?->name_az])->filter()->implode(' · ');
    // Ətrin növü (Eau De Parfum / Eau De Toilette...) və cinsi (Kişi / Qadın üçün, Unisex) — kuryer düzgün malı götürsün
    $kind = fn ($item) => collect([$item->product?->type?->name_az, $item->product?->genders->pluck('name_az')->implode(', ')])->filter()->implode(' · ');
@endphp
@extends('courier-app.layout', ['tab' => 'orders'])
@section('title', $order->order_no)
@section('back', route('kuryer.home'))

@section('content')
    @if($order->courier_note)
        {{-- Operatorun kuryerə qeydi — hər şeydən əvvəl --}}
        <div class="note" role="note">
            <div class="note__title">@include('courier-app._icon', ['name' => 'warning']) Operatorun qeydi</div>
            <div>{{ $order->courier_note }}</div>
        </div>
    @endif

    @if($cancelled)
        <div class="flash flash--error">
            <b>Sifariş ləğv edilib.</b>
            {{ $returning->isNotEmpty() ? 'Götürdüyünüz məhsulları aşağıdakı depoya qaytarın və "Qaytardım" basın.' : 'Sizdən heç nə tələb olunmur.' }}
        </div>
    @else
        <ol class="steps" aria-label="Mərhələlər">
            @foreach($steps as $stepCode => $label)
                <li class="{{ $reached !== false && $loop->index <= $reached ? 'is-done' : '' }} {{ $stepCode === $code ? 'is-current' : '' }}">{{ $label }}</li>
            @endforeach
        </ol>
    @endif

    {{-- 1. Depolardan götürmə — yola çıxandan sonra gizlənir --}}
    @if($code === 'courier_assigned')
        <h2 class="section">Depolardan götürmə</h2>
        @php
            // Depodan hər şey götürülübsə — pul ödənsə də, ödənməsə də — depo gizlənir, yalnız "götürüldü" sətri qalır
            $doneGroups = $byWarehouse->filter(fn ($rows) => $rows->every(fn ($r) => $r['a']->status === Part::PICKED));
            $pickedCount = $doneGroups->flatten(1)->sum(fn ($r) => $r['a']->quantity);
        @endphp
        @if($pickedCount)
            <div class="done">@include('courier-app._icon', ['name' => 'check']) Götürüldü: {{ $pickedCount }} ədəd</div>
        @endif
        @foreach($byWarehouse->diffKeys($doneGroups) as $rows)
            @php
                $w = $rows->first()['a']->warehouse;
                $allPicked = $rows->every(fn ($r) => $r['a']->status === Part::PICKED);
            @endphp
            <div class="card wh">
                <div class="wh__name">{{ $w->name_az }}</div>
                {{-- Mal götürüləndən sonra ünvan / əlaqə lazım deyil — yalnız ödəniş qalıb --}}
                @unless($allPicked)
                    @if($w->address)<a class="link-map" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($w->address) }}" target="_blank" rel="noopener">@include('courier-app._icon', ['name' => 'map']) {{ $w->address }}</a>@endif
                    @if($w->contact_name)<div class="muted">{{ $w->contact_name }}</div>@endif
                @endunless

                @foreach($rows as ['item' => $item, 'a' => $part])
                    @php
                        $left = (int) round($part->quantity * (float) $part->unit_cost * 100) - ($paid[$part->id] ?? 0);
                        $canPay = $part->courier_pays; // "pul vermə" — kuryer pulla bağlı heç nə görmür, yalnız "Götürdüm"
                    @endphp
                    <div class="part part--{{ $part->status }}">
                        <div class="product">
                            @include('courier-app._thumb', ['product' => $item->product])
                            <div class="product__text">
                                <div class="product__name">{{ $item->product?->name }}@if($item->gift_wrap) <span class="badge badge--outline">Hədiyyəlik</span>@endif</div>
                                <div class="muted">{{ $variant($item) }}</div>
                                @if($k = $kind($item))<div class="product__kind">{{ $k }}</div>@endif
                            </div>
                            <div class="product__qty">× {{ $part->quantity }}</div>
                        </div>
                        <div class="part__info">
                            <span class="badge {{ ['picked' => 'badge--ok', 'problem' => 'badge--danger'][$part->status] ?? 'badge--outline' }}">{{ $part->label() }}</span>
                            @if(!$canPay)
                            @elseif($left > 0)
                                <span>Depoya ödəniş: <b>{{ $money($left) }}</b></span>
                            @else
                                <span class="ok">Depoya ödənilib</span>
                            @endif
                        </div>
                        @if($part->status === Part::PROBLEM)
                            <div class="part__problem">{{ $part->logs->last()?->note }} — operator həll edir.</div>
                        @else
                            <div class="actions">
                                {{-- Götürmə: ödəniş qalıbsa iki yol — tam ödəyib götürmək və ya ödənişsiz (depoya borc qalır).
                                     data-photo — basanda tətbiqin kamerası açılır, şəkil formaya qoşulur (app.js), server şəkilsiz qəbul etmir --}}
                                @if($part->status !== Part::PICKED)
                                    @if($canPay && $left > 0)
                                        <form method="POST" action="{{ route('kuryer.pay', [$order, $part->id]) }}" enctype="multipart/form-data" data-once data-photo>@csrf<input type="hidden" name="pick" value="1"><input type="file" name="photo" accept="image/*" hidden data-photo-input>
                                            <button class="btn btn--primary btn--block">{{ $money($left) }} ödədim, götürdüm</button></form>
                                        <form method="POST" action="{{ route('kuryer.pick', [$order, $part->id]) }}" enctype="multipart/form-data" data-once data-photo>@csrf<input type="file" name="photo" accept="image/*" hidden data-photo-input><button class="btn btn--outline btn--block">Ödənişsiz götürdüm</button></form>
                                    @else
                                        <form method="POST" action="{{ route('kuryer.pick', [$order, $part->id]) }}" enctype="multipart/form-data" data-once data-photo>@csrf<input type="file" name="photo" accept="image/*" hidden data-photo-input><button class="btn btn--primary btn--block">Götürdüm</button></form>
                                    @endif
                                    <details class="more">
                                        <summary class="btn btn--danger-outline btn--block">Problem</summary>
                                        <form method="POST" action="{{ route('kuryer.problem', [$order, $part->id]) }}" data-once class="more__body">
                                            @csrf
                                            @foreach(Part::PROBLEM_TYPES as $value => $label)
                                                <label class="choice"><input type="radio" name="problem_type" value="{{ $value }}" required><span>{{ $label }}</span></label>
                                            @endforeach
                                            <textarea name="note" rows="2" maxlength="2000" class="field" placeholder="Qısa izah"></textarea>
                                            <button class="btn btn--danger btn--block">Operatora bildir</button>
                                        </form>
                                    </details>
                                @elseif($canPay && $left > 0)
                                    {{-- Ödənişsiz götürülüb — sonradan ödəniş --}}
                                    <form method="POST" action="{{ route('kuryer.pay', [$order, $part->id]) }}" data-once>@csrf<button class="btn btn--outline btn--block">{{ $money($left) }} ödədim</button></form>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- Başqa kuryerə ötür (depolar uzaqdırsa) — heç nə götürülməyibsə --}}
        @if(!$transferBlock && $couriers->isNotEmpty())
            <details class="more">
                <summary class="btn btn--ghost btn--block">Başqa kuryerə ötür</summary>
                <form method="POST" action="{{ route('kuryer.transfer', $order) }}" data-once data-confirm="Sifariş {{ $order->order_no }} seçilən kuryerə ötürülsün?" class="more__body">
                    @csrf
                    <label class="label" for="transferCourier">Kuryer</label>
                    <select id="transferCourier" name="courier_id" class="field" required>
                        <option value="">— seçin —</option>
                        @foreach($couriers as $c)<option value="{{ $c->id }}">{{ trim($c->full_name) }}</option>@endforeach
                    </select>
                    <button class="btn btn--primary btn--block">Ötür</button>
                    <div class="muted">Sifariş sizin siyahınızdan çıxacaq.</div>
                </form>
            </details>
        @elseif($transferBlock && !str_starts_with($transferBlock, 'Yola'))
            <p class="muted">{{ $transferBlock }}</p>
        @endif
    @endif

    {{-- Qapıda imtina edilən məhsullar — depoya qaytarılacaq --}}
    @if($returning->isNotEmpty())
        <h2 class="section">Depoya qaytarılacaq</h2>
        @foreach($returning as $rows)
            @php $w = $rows->first()['a']->warehouse; @endphp
            <div class="card wh wh--return">
                <div class="wh__name">{{ $w->name_az }}</div>
                @if($w->address)<a class="link-map" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($w->address) }}" target="_blank" rel="noopener">@include('courier-app._icon', ['name' => 'map']) {{ $w->address }}</a>@endif
                @foreach($rows as ['item' => $item, 'a' => $part])
                    @php $paidBack = $part->courier_pays ? ($paid[$part->id] ?? 0) : 0; // pulu şirkət ödəyibsə — depo şirkətə qaytarır @endphp
                    <div class="part part--returning">
                        <div class="product">
                            @include('courier-app._thumb', ['product' => $item->product])
                            <div class="product__text">
                                <div class="product__name">{{ $item->product?->name }}@if($item->gift_wrap) <span class="badge badge--outline">Hədiyyəlik</span>@endif</div>
                                <div class="muted">{{ $variant($item) }}</div>
                                @if($k = $kind($item))<div class="product__kind">{{ $k }}</div>@endif
                            </div>
                            <div class="product__qty">× {{ $part->quantity }}</div>
                        </div>
                        <form method="POST" action="{{ route('kuryer.returned', [$order, $part->id]) }}" data-once class="actions">
                            @csrf
                            @if($paidBack > 0)
                                {{-- Bu mala depoya pul ödənilib: depo pulu qaytardısa kuryerin hesabına geri yazılır --}}
                                <label class="choice"><input type="checkbox" name="refunded" value="1" checked><span>Depo <b>{{ $money($paidBack) }}</b> pulu mənə qaytardı</span></label>
                            @endif
                            <button class="btn btn--warn btn--block">Qaytardım</button>
                            @if($paidBack > 0)<div class="muted">Pulu qaytarmayıbsa işarəni götürün — operator depoyla sonra hesablaşacaq.</div>@endif
                        </form>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    @if(!$cancelled)
        @if($order->gift_wrap && $code !== 'delivered')
            @php $wrapped = $order->items->filter(fn ($i) => $i->gift_wrap && $i->activeQuantity() > 0); @endphp
            <div class="note note--info">@include('courier-app._icon', ['name' => 'gift']) <span><b>Hədiyyəlik qablaşdırma:</b>
                @if($wrapped->isNotEmpty())
                    {{ $wrapped->map(fn ($i) => ($i->product?->name ?? 'Məhsul').' × '.$i->activeQuantity())->implode(', ') }} — bükün və loqolu çantaya qoyun.
                @else
                    məhsulları bükün və loqolu çantaya qoyun.
                @endif
            </span></div>
        @endif

        {{-- 2. Müştəri --}}
        <h2 class="section">Müştəri</h2>
        <div class="card client">
            <div class="client__head">
                <div class="client__name">{{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
                <div class="client__calls">
                    @if($phone)<a href="tel:+{{ preg_replace('/\D+/', '', $phone) }}" class="btn btn--primary btn--icon" aria-label="Müştəriyə zəng">@include('courier-app._icon', ['name' => 'phone'])</a>@endif
                    @if($phone2)<a href="tel:+{{ preg_replace('/\D+/', '', $phone2) }}" class="btn btn--outline btn--icon" aria-label="Ehtiyat nömrəyə zəng: {{ $phone2 }}">@include('courier-app._icon', ['name' => 'phone'])<small>2</small></a>@endif
                </div>
            </div>

            @if($a?->on_call)
                {{-- Müştəri hərəkətdədir: zəng edib yeri öyrənin, görüş yerini yazın (operator da görür) --}}
                <div class="muted">{{ \App\Models\Customer\CustomerAddress::ON_CALL_TEXT }}</div>
                @if($order->meeting_place)
                    <div class="client__line"><b>Görüş yeri:</b> {{ $order->meeting_place }}</div>
                    <div class="row-btns"><a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($order->meeting_place) }}" target="_blank" rel="noopener" class="btn btn--outline">@include('courier-app._icon', ['name' => 'map']) Google Maps</a></div>
                @endif
                @if(!in_array($code, ['delivered', 'cancelled'], true))
                    <form method="POST" action="{{ route('kuryer.meeting-place', $order) }}" data-once class="stack">
                        @csrf
                        <label class="label" for="meetingPlace">{{ $order->meeting_place ? 'Görüş yerini dəyiş' : 'Görüş yeri (müştəri ilə danışandan sonra)' }}</label>
                        <input id="meetingPlace" name="meeting_place" maxlength="500" class="field" value="{{ $order->meeting_place }}" placeholder="Məs.: 28 May metrosunun çıxışı, 15:00" required>
                        <button class="btn btn--outline btn--block">Yadda saxla</button>
                    </form>
                @endif
            @else
                @if($addressLine)<div class="client__line">{{ $addressLine }}</div>@endif
                @if($addressExtra)<div class="muted">{{ $addressExtra }}</div>@endif
                @if($a?->note)<div class="muted">{{ $a->note }}</div>@endif
                @if($a)
                    {{-- Naviqasiya: müştəri xəritədə nöqtə seçibsə — dəqiq nöqtə, yoxsa ünvan mətni ilə --}}
                    <div class="row-btns">
                        <a href="{{ $a->directionsUrl() }}" target="_blank" rel="noopener" class="btn btn--outline">@include('courier-app._icon', ['name' => 'map']) Google Maps</a>
                        <a href="{{ $a->wazeUrl() }}" target="_blank" rel="noopener" class="btn btn--outline">Waze</a>
                    </div>
                    @if($a->hasLocation())<div class="ok small">Müştəri xəritədə dəqiq nöqtə seçib</div>@endif
                @endif
            @endif
            @if($order->customer_note)<div class="client__line"><b>Qeyd:</b> {{ $order->customer_note }}</div>@endif

            <div class="collect {{ $collect > 0 ? 'collect--cash' : 'collect--paid' }}">
                @if($collect > 0)
                    <span>Müştəridən alınacaq (nağd)</span><b>{{ number_format($collect, 2) }} AZN</b>
                @else
                    <span>Ödəniş</span><b>Ödənilib — pul alınmır</b>
                @endif
            </div>
        </div>

        {{-- 3. Çatdırılma — əsas düymə ekranın aşağısında --}}
        <div class="deliver">
            @if($code === 'courier_assigned')
                @if($deliveryBlock)<p class="muted center">{{ $deliveryBlock }}</p>@endif
                <form method="POST" action="{{ route('kuryer.start', $order) }}" data-once>@csrf
                    <button class="btn btn--primary btn--block btn--lg" @disabled($deliveryBlock)>Çatdırılmaya başladım</button>
                </form>
            @elseif($code === 'sent')
                <form method="POST" action="{{ route('kuryer.deliver', $order) }}" data-once>
                    @csrf
                    {{-- Məbləğ yazılmır: göstərilən məbləğ göndərilir, server cari məbləğlə tutuşdurur (arada dəyişibsə — xəta) --}}
                    @if($collect > 0)
                        <input type="hidden" name="collected" value="{{ number_format($collect, 2, '.', '') }}">
                        <button class="btn btn--success btn--block btn--lg">{{ number_format($collect, 2) }} AZN nağd aldım — təhvil verdim</button>
                        <p class="muted center">Məbləğ fərqlidirsə, aşağıdan "Problem" bildirin.</p>
                    @else
                        <button class="btn btn--success btn--block btn--lg">Təhvil verdim</button>
                    @endif
                </form>

                {{-- Qapıda imtina: müştəri məhsullardan birini götürmür --}}
                @php $refusable = $order->items->filter(fn ($i) => $i->activeQuantity() > 0); @endphp
                @if(!$doorBlock && $refusable->sum(fn ($i) => $i->activeQuantity()) > 1)
                    <details class="more">
                        <summary class="btn btn--warn-outline btn--block">Müştəri məhsuldan imtina etdi</summary>
                        <div class="more__body">
                            @foreach($refusable as $ri)
                                <form method="POST" action="{{ route('kuryer.refuse', [$order, $ri]) }}" data-once class="refuse">
                                    @csrf
                                    <div class="product">
                                        @include('courier-app._thumb', ['product' => $ri->product])
                                        <div class="product__text">
                                            <div class="product__name">{{ $ri->product?->name }}@if($ri->gift_wrap) <span class="badge badge--outline">Hədiyyəlik</span>@endif</div>
                                            <div class="muted">{{ $variant($ri) }} · {{ number_format((float) $ri->unit_price, 2) }} AZN</div>
                                            @if($k = $kind($ri))<div class="product__kind">{{ $k }}</div>@endif
                                        </div>
                                    </div>
                                    <select name="quantity" class="field" aria-label="Neçə ədəd götürmədi">
                                        @for($q = 1; $q <= $ri->activeQuantity(); $q++)<option value="{{ $q }}">{{ $q }} ədəd götürmədi</option>@endfor
                                    </select>
                                    <input type="text" name="note" maxlength="2000" class="field" placeholder="Qeyd (istəyə görə)">
                                    <button class="btn btn--warn btn--block">Qeyd et</button>
                                </form>
                            @endforeach
                            <div class="muted">Məbləğ avtomatik azalacaq, məhsulu depoya qaytaracaqsınız.</div>
                        </div>
                    </details>
                @endif
                <details class="more">
                    <summary class="btn btn--danger-outline btn--block">Problem (müştəri yoxdur, qəbul etmir...)</summary>
                    <form method="POST" action="{{ route('kuryer.delivery-problem', $order) }}" data-once class="more__body">
                        @csrf
                        <textarea name="note" rows="3" maxlength="2000" class="field" placeholder="Nə baş verdi?" required></textarea>
                        <button class="btn btn--danger btn--block">Operatora bildir</button>
                    </form>
                </details>
            @elseif($code === 'delivered')
                <div class="flash flash--ok center">Sifariş təhvil verilib.</div>
            @endif
        </div>
    @endif
@endsection
