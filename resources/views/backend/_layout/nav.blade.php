<div class="nav-content d-flex">
    <!-- Logo Start -->
    <div class="logo position-relative">
        <a href="/admin">
            <div class="img"></div>
        </a>
    </div>
    <!-- Logo End -->

    <!-- User Menu Start -->
    <div class="user-container d-flex">
        <a href="#" class="d-flex user position-relative" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img class="profile" alt="profile" src="{{asset('backend/img/profile/male.png')}}" />
            <div class="name">{{ admin()->name." ".admin()->surname }}</div>
        </a>
        <div class="dropdown-menu dropdown-menu-end user-menu wide">
            <div class="row mb-1 ms-0 me-0">
                <div class="col-6 pe-1 ps-1">
                    <ul class="list-unstyled">
                        <li>
                            <a href="{{route('admin.logout')}}">
                                <i data-acorn-icon="logout" class="me-2" data-acorn-size="17"></i>
                                <span class="align-middle">Çıxış</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- User Menu End -->


    <!-- Icons Menu Start -->
    <ul class="list-unstyled list-inline text-center menu-icons">
        <li class="list-inline-item">
            <a href="#" data-bs-toggle="modal" data-bs-target="#searchPagesModal">
                <i data-acorn-icon="search" data-acorn-size="18"></i>
            </a>
        </li>
        <li class="list-inline-item">
            <a href="#" id="pinButton" class="pin-button">
                <i data-acorn-icon="lock-on" class="unpin" data-acorn-size="18"></i>
                <i data-acorn-icon="lock-off" class="pin" data-acorn-size="18"></i>
            </a>
        </li>
        <li class="list-inline-item">
            <a href="#" id="colorButton">
                <i data-acorn-icon="light-on" class="light" data-acorn-size="18"></i>
                <i data-acorn-icon="light-off" class="dark" data-acorn-size="18"></i>
            </a>
        </li>
        @can('crm')
            {{-- Bildirişlər (Acorn notification-dropdown): asan sifariş, hissə-hissə müraciəti, sayt sifarişi — admin-notifications.js --}}
            <li class="list-inline-item" id="adminNotifications"
                data-feed-url="{{ route('admin.notifications.feed') }}" data-read-url="{{ route('admin.notifications.read') }}">
                <a href="#" data-bs-toggle="dropdown" data-bs-target="#notifications" aria-haspopup="true" aria-expanded="false"
                   class="notification-button" aria-label="Bildirişlər">
                    <div class="position-relative d-inline-flex">
                        <i data-acorn-icon="bell" data-acorn-size="18"></i>
                        <span class="position-absolute notification-dot rounded-xl d-none" data-notifications-dot></span>
                        <span class="admin-notifications__count d-none" data-notifications-count></span>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end wide notification-dropdown scroll-out" id="notifications">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold">Bildirişlər</span>
                        <button type="button" class="btn btn-sm btn-link p-0" data-notifications-mute>Səsi söndür</button>
                    </div>
                    <div class="scroll">
                        <ul class="list-unstyled border-last-none mb-0" data-notifications-list>
                            <li class="text-muted">Bildiriş yoxdur</li>
                        </ul>
                    </div>
                </div>
            </li>
        @endcan
    </ul>
    <!-- Icons Menu End -->

    <!-- Menu Start -->
    <div class="menu-container flex-grow-1">
        <ul id="menu" class="menu">
            @include('backend._layout.menu_items', ['items' => app(\App\Services\AdminMenuService::class)->items()])
        </ul>
    </div>
    <!-- Menu End -->

    <!-- Mobile Buttons Start -->
    <div class="mobile-buttons-container">
        <!-- Scrollspy Mobile Button Start -->
        <a href="#" id="scrollSpyButton" class="spy-button" data-bs-toggle="dropdown">
            <i data-acorn-icon="menu-dropdown"></i>
        </a>
        <!-- Scrollspy Mobile Button End -->

        <!-- Scrollspy Mobile Dropdown Start -->
        <div class="dropdown-menu dropdown-menu-end" id="scrollSpyDropdown"></div>
        <!-- Scrollspy Mobile Dropdown End -->

        <!-- Menu Button Start -->
        <a href="#" id="mobileMenuButton" class="menu-button">
            <i data-acorn-icon="menu"></i>
        </a>
        <!-- Menu Button End -->
    </div>
    <!-- Mobile Buttons End -->
</div>
<div class="nav-shadow"></div>
