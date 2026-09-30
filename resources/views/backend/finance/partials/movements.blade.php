{{--
  Pul hərəkətləri cədvəli (DataTables). İki tarix: real (pulun verildiyi) və qeydə alınma (sistemə yazıldığı).
  Parametrlər: $movements
--}}
<div class="row">
    <div class="col-12 col-sm-5 col-lg-3 col-xxl-2 mb-1">
        <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 border border-separator bg-foreground search-sm">
            <input class="form-control form-control-sm datatable-search" placeholder="Axtar" data-datatable="#datatableHover"/>
            <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
            <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
        </div>
    </div>
    <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
        <div class="d-inline-block">
            <div class="d-inline-block datatable-export" data-datatable="#datatableHover">
                <button class="btn btn-icon btn-icon-only btn-outline-muted btn-sm dropdown" data-bs-toggle="dropdown" type="button" data-bs-offset="0,3"><i data-acorn-icon="download"></i></button>
                <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                    <button class="dropdown-item export-excel" type="button">Excel</button>
                    <button class="dropdown-item export-cvs" type="button">Cvs</button>
                </div>
            </div>
            <div class="dropdown-as-select d-inline-block datatable-length" data-datatable="#datatableHover">
                <button class="btn btn-outline-muted btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" data-bs-offset="0,3">20 Nəticə</button>
                <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                    <a class="dropdown-item" href="#">10 Nəticə</a>
                    <a class="dropdown-item active" href="#">20 Nəticə</a>
                    <a class="dropdown-item" href="#">50 Nəticə</a>
                    <a class="dropdown-item" href="#">100 Nəticə</a>
                </div>
            </div>
        </div>
    </div>
</div>
<table class="data-table data-table-pagination data-table-standard responsive nowrap hover" id="datatableHover" data-order='[[ 1, "desc" ]]' data-page-length="20">
    <thead><tr>
        @foreach(['#', 'Real tarix', 'Qeydə alınıb', 'Növ', 'Haradan', 'Haraya', 'Məbləğ', 'Sifariş', 'Qeyd', 'İcraçı', ''] as $th)<th class="text-muted text-small text-uppercase">{{ $th }}</th>@endforeach
    </tr></thead>
    <tbody>
    @foreach($movements as $m)
        <tr class="{{ $m->kind === 'reversal' || $m->reversedBy ? 'finance-row--reversed' : '' }}">
            <td>{{ $m->id }}</td>
            <td data-order="{{ $m->occurred_at->timestamp }}">{{ $m->occurred_at->format('d.m.Y H:i') }}</td>
            <td data-order="{{ $m->created_at?->timestamp }}" class="text-alternate">
                {{ $m->created_at?->format('d.m.Y H:i') }}
                @if($m->isBackdated())<span class="d-block small text-warning">sonradan yazılıb</span>@endif
            </td>
            <td>{{ $m->kindLabel() }}@if($m->reversedBy)<span class="d-block small text-danger">əks olunub (#{{ $m->reversedBy->id }})</span>@endif</td>
            <td class="text-alternate">{{ $m->from?->name }}</td>
            <td class="text-alternate">{{ $m->to?->name }}</td>
            <td data-order="{{ $m->amount }}" class="text-nowrap fw-bold">{{ number_format((float) $m->amount, 2) }} AZN</td>
            <td>@if($m->order)<a href="{{ route('admin.crm.order', [$m->order->customer_id, $m->order->id]) }}#settlements">{{ $m->order->order_no }}</a>@else — @endif</td>
            <td class="text-alternate finance-note">{{ $m->note }}</td>
            <td class="text-alternate">{{ $m->user?->full_name ?? 'Sistem' }}</td>
            <td class="text-end">
                @if($m->kind !== 'reversal' && !$m->reversedBy)
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseMovement"
                            data-action="{{ route('admin.finance.reverse', $m) }}" data-title="#{{ $m->id }} · {{ $m->kindLabel() }} · {{ number_format((float) $m->amount, 2) }} AZN">Əks et</button>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
