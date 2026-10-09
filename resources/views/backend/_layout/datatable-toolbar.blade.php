{{-- Datatable üst paneli: axtarış, çap, export, nəticə sayı. Parametr: $table — cədvəlin ID-si (# ilə) --}}
<div class="row">
    <div class="col-sm-12 col-md-5 col-lg-3 col-xxl-2 mb-1">
        <div class="d-inline-block float-md-start me-1 mb-1 search-input-container w-100 shadow bg-foreground">
            <input class="form-control datatable-search" placeholder="Axtar" data-datatable="{{ $table }}">
            <span class="search-magnifier-icon"><i data-acorn-icon="search"></i></span>
            <span class="search-delete-icon d-none"><i data-acorn-icon="close"></i></span>
        </div>
    </div>

    <div class="col-sm-12 col-md-7 col-lg-9 col-xxl-10 text-end mb-1">
        <div class="d-inline-block">
            <button class="btn btn-icon btn-icon-only btn-foreground-alternate shadow datatable-print"
                    data-bs-delay="0" data-datatable="{{ $table }}" data-bs-toggle="tooltip"
                    data-bs-placement="top" title="Çap et" type="button">
                <i data-acorn-icon="print"></i>
            </button>

            <div class="d-inline-block datatable-export" data-datatable="{{ $table }}">
                <button class="btn p-0" data-bs-toggle="dropdown" type="button" data-bs-offset="0,3">
                    <span class="btn btn-icon btn-icon-only btn-foreground-alternate shadow dropdown"
                          data-bs-delay="0" data-bs-placement="top" data-bs-toggle="tooltip" title="Export">
                        <i data-acorn-icon="download"></i>
                    </span>
                </button>
                <div class="dropdown-menu shadow dropdown-menu-end">
                    <button class="dropdown-item export-copy" type="button">Copy</button>
                    <button class="dropdown-item export-excel" type="button">Excel</button>
                    <button class="dropdown-item export-cvs" type="button">Cvs</button>
                </div>
            </div>

            <div class="dropdown-as-select d-inline-block datatable-length" data-datatable="{{ $table }}" data-childSelector="span">
                <button class="btn p-0 shadow" type="button" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false" data-bs-offset="0,3">
                    <span class="btn btn-foreground-alternate dropdown-toggle" data-bs-toggle="tooltip"
                          data-bs-placement="top" data-bs-delay="0" title="Nəticə sayı">
                        20 Nəticə
                    </span>
                </button>
                <div class="dropdown-menu shadow dropdown-menu-end">
                    <a class="dropdown-item" href="#">10 Nəticə</a>
                    <a class="dropdown-item active" href="#">20 Nəticə</a>
                    <a class="dropdown-item" href="#">50 Nəticə</a>
                    <a class="dropdown-item" href="#">100 Nəticə</a>
                </div>
            </div>
        </div>
    </div>
</div>
