    @php($editable = $order->customer_id && in_array($order->status?->code, ['new', 'confirmed', 'preparing'], true))
    @unless($editable)<div class="alert alert-info">Bu sifarişin cari mərhələsində təminat dəyişdirilə bilməz.</div>@endunless
    <div class="card mb-4"><div class="card-body">
        <h2 class="h5">Məhsullar üzrə seçim</h2>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Məhsul</th><th>Tələb</th><th>Seçilib</th><th>Çatışmır</th><th>Seçilmiş alış məbləği</th></tr></thead>
            <tbody>
            @foreach($order->items as $item)
                @php($active = $item->allocations->where('status', '!=', 'cancelled'))
                <tr><td>{{ $item->product?->name ?? 'Silinmiş məhsul' }} · {{ $item->variant?->size?->name_az }}</td><td>{{ $item->quantity }}</td><td>{{ $active->sum('quantity') }}</td><td>{{ max(0, $item->quantity - $active->sum('quantity')) }}</td><td>{{ number_format($active->sum(fn($a) => $a->quantity * (float) $a->unit_cost), 2) }} AZN</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="text-muted mb-0">Anbar seçimi rezervasiya təsdiqi deyil. Müştərinin satış qiyməti dəyişmir.</p>
    </div></div>

    @if($editable)
    <div class="card mb-4"><div class="card-body">
        <h2 class="h5">Sorğu yarat</h2>
        <p class="text-muted">Seçilmiş məhsullar hər seçilən anbar üçün ayrıca sorğuya daxil edilir. Fərqli məhsul siyahısı üçün ayrıca sorğu yaradın. Bu mərhələdə bildiriş avtomatik göndərilmir.</p>
        <form method="POST" action="{{ route('admin.procurement.requests.store', $order) }}">
            @csrf
            <div class="row g-3">
                <fieldset class="col-md-6"><legend class="h6">Məhsullar</legend>
                @foreach($order->items as $item)
                    <label class="d-block mb-2"><input type="checkbox" name="item_ids[]" value="{{ $item->id }}"> {{ $item->product?->name }} · {{ $item->variant?->size?->name_az }} × {{ $item->quantity }}</label>
                @endforeach
                </fieldset>
                <fieldset class="col-md-6"><legend class="h6">Anbarlar</legend>
                @forelse($warehouses as $warehouse)
                    <label class="d-block mb-2"><input type="checkbox" name="warehouse_ids[]" value="{{ $warehouse->id }}"> {{ $warehouse->name_az }}</label>
                @empty<p>Əvvəlcə anbar əlavə edin.</p>@endforelse
                </fieldset>
            </div>
            <button class="btn btn-primary mt-3" @disabled($warehouses->isEmpty())>Sorğunu qeydə al</button>
        </form>
    </div></div>
    @endif

    <h2 class="h4">Təkliflərin müqayisəsi</h2>
    @foreach($order->items as $item)
        <div class="card mb-3"><div class="card-body">
            <h3 class="h6">{{ $item->product?->name }} · {{ $item->variant?->size?->name_az }} × {{ $item->quantity }}</h3>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>Anbar</th><th>Cavab</th><th>Vahid qiymət</th><th>Seçim</th></tr></thead><tbody>
                @foreach($requests->groupBy('warehouse_id') as $warehouseRequests)
                    @php($responses = $warehouseRequests->flatMap(fn($r) => $r->items)->where('order_item_id', $item->id))
                    @continue($responses->isEmpty())
                    @php($offer = $responses->flatMap(fn($r) => $r->offers)->sortByDesc('id')->first())
                    <tr><td>{{ $warehouseRequests->first()->warehouse->name_az }}</td>
                        <td>{{ !$offer ? 'Cavab gözlənilir' : ($offer->available_quantity ? $offer->available_quantity.' ədəd var' : 'Yoxdur') }}</td>
                        <td>{{ $offer?->unit_cost !== null ? number_format((float) $offer->unit_cost, 2).' AZN' : '—' }}</td>
                        <td>@if($editable && $offer?->available_quantity)
                            <form method="POST" action="{{ route('admin.procurement.allocations.store', $order) }}" class="d-flex gap-2">
                                @csrf <input type="hidden" name="offer_id" value="{{ $offer->id }}">
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input aria-label="Seçilən miqdar" type="number" name="quantity" min="1" max="{{ $offer->available_quantity }}" value="1" class="form-control" style="max-width:90px" required>
                                <button class="btn btn-outline-primary text-nowrap">Seç</button>
                            </form>
                        @endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div></div>
    @endforeach

    <h2 class="h4">Anbar seçimləri və tarixçə</h2>
    @foreach($order->items as $item)
        @foreach($item->allocations as $allocation)
        <div class="card mb-3"><div class="card-body">
            <strong>{{ $item->product?->name }} · {{ $item->variant?->size?->name_az }} — {{ $allocation->warehouse->name_az }}</strong>
            <p>{{ $allocation->quantity }} ədəd × {{ number_format((float) $allocation->unit_cost, 2) }} AZN · {{ $allocation->status === 'cancelled' ? 'Seçim ləğv edilib' : 'Anbar seçilib' }}</p>
            @if($editable && $allocation->status === 'selected')
                <form method="POST" action="{{ route('admin.procurement.allocations.cancel', [$order, $allocation]) }}" class="d-flex gap-2 mb-2">
                    @csrf <input name="note" aria-label="Seçimin ləğv səbəbi" placeholder="Anbar seçiminin ləğv səbəbi" maxlength="2000" class="form-control" required>
                    <button class="btn btn-outline-danger text-nowrap">Seçimi ləğv et</button>
                </form>
            @endif
            <details><summary>Tarixçə</summary><ul class="mt-2">@foreach($allocation->logs as $log)<li>{{ $log->created_at->format('d.m.Y H:i') }} · {{ $log->to_status === 'cancelled' ? 'Seçim ləğv edilib' : 'Anbar seçilib' }} · Əməkdaş #{{ $log->user_id }} {{ $log->note }}</li>@endforeach</ul></details>
        </div></div>
        @endforeach
    @endforeach

    <h2 class="h4">Sorğular və cavabların daxil edilməsi</h2>
    @forelse($requests as $warehouseRequest)
    <div class="card mb-4"><div class="card-body">
        <h3 class="h5">{{ $warehouseRequest->warehouse->name_az }} · Sorğu #{{ $warehouseRequest->id }}</h3>
        <p class="text-muted">{{ $warehouseRequest->created_at->format('d.m.Y H:i') }} · {{ $warehouseRequest->warehouse->phone }}</p>
        @foreach($warehouseRequest->items as $requestItem)
            <div class="border rounded p-3 mb-3">
                <h4 class="h6">{{ $requestItem->orderItem->product?->name }} · {{ $requestItem->orderItem->variant?->size?->name_az }} · Tələb: {{ $requestItem->requested_quantity }}</h4>
                @if($editable)
                <form method="POST" action="{{ route('admin.procurement.offers.store', [$order, $requestItem]) }}" class="row g-2">
                    @csrf
                    <div class="col-sm-3"><label class="form-label">Təmin edilən say (yoxdursa 0)<input type="number" name="available_quantity" min="0" max="{{ $requestItem->requested_quantity }}" class="form-control" required></label></div>
                    <div class="col-sm-3"><label class="form-label">1 ədədin qiyməti, AZN<input type="number" name="unit_cost" min="0.01" step="0.01" class="form-control"></label></div>
                    <div class="col-sm-3"><label class="form-label">Cavabın mənbəyi<select name="source" class="form-select"><option value="phone">Telefon</option><option value="whatsapp">WhatsApp</option><option value="telegram">Telegram</option><option value="manual">Digər</option></select></label></div>
                    <div class="col-sm-3"><label class="form-label">Qeyd<input name="note" maxlength="2000" class="form-control"></label></div>
                    <div class="col-12"><button class="btn btn-outline-primary">Cavabı qeydə al</button></div>
                </form>
                @endif
                <ul class="mt-3 mb-0">
                    @forelse($requestItem->offers as $offer)
                        <li>{{ $offer->created_at->format('d.m.Y H:i') }} · {{ $offer->available_quantity ? $offer->available_quantity.' ədəd — '.number_format((float) $offer->unit_cost, 2).' AZN/ədəd' : 'Yoxdur' }} · {{ ['phone'=>'Telefon','whatsapp'=>'WhatsApp','telegram'=>'Telegram','manual'=>'Digər'][$offer->source] ?? $offer->source }} · Əməkdaş #{{ $offer->recorded_by }} @if($loop->first)<strong>(Son cavab)</strong>@endif {{ $offer->note }}</li>
                    @empty<li class="text-muted">Cavab gözlənilir</li>@endforelse
                </ul>
            </div>
        @endforeach
    </div></div>
    @empty<p class="text-muted">Hələ sorğu yaradılmayıb.</p>@endforelse
