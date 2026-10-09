{{-- Kart içindəki datatable paneli: axtarış, export, nəticə sayı. Parametr: $table — cədvəlin ID-si (# ilə) --}}
<div class="row">
    <div class="col-12 col-sm-5 col-lg-3 col-xxl-2 mb-1">
        <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 border border-separator bg-foreground search-sm">
            <input class="form-control form-control-sm datatable-search" placeholder="Axtar" data-datatable="{{ $table }}">
            <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
            <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
        </div>
    </div>
    <div class="col-12 col-sm-7 col-lg-9 col-xxl-10 text-end mb-1">
        <div class="d-inline-block">
            <div class="d-inline-block datatable-export" data-datatable="{{ $table }}">
                <button class="btn btn-icon btn-icon-only btn-outline-muted btn-sm dropdown"
                        data-bs-toggle="dropdown" type="button" data-bs-offset="0,3" aria-label="İxrac et">
                    <i data-acorn-icon="download"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                    <button class="dropdown-item export-excel" type="button">Excel</button>
                    <button class="dropdown-item export-cvs" type="button">CSV</button>
                </div>
            </div>
            <div class="dropdown-as-select d-inline-block datatable-length" data-datatable="{{ $table }}">
                <button class="btn btn-outline-muted btn-sm dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="0,3">
                    50 Nəticə
                </button>
                <div class="dropdown-menu dropdown-menu-sm dropdown-menu-end">
                    @foreach([10, 20, 50, 100] as $limit)
                        <a class="dropdown-item {{ $limit === 50 ? 'active' : '' }}" href="#">{{ $limit }} Nəticə</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
