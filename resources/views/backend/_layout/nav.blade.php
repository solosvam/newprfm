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
            <img class="profile" alt="profile" src="{{asset('backend/img/profile/profile-9.webp')}}" />
            <div class="name">{{ admin()->name." ".admin()->surname }}</div>
        </a>
        <div class="dropdown-menu dropdown-menu-end user-menu wide">
            <div class="row mb-3 ms-0 me-0">
                <div class="col-12 ps-1 mb-2">
                    <div class="text-extra-small text-primary">ACCOUNT</div>
                </div>
                <div class="col-6 ps-1 pe-1">
                    <ul class="list-unstyled">
                        <li>
                            <a href="#">User Info</a>
                        </li>
                        <li>
                            <a href="#">Preferences</a>
                        </li>
                        <li>
                            <a href="#">Calendar</a>
                        </li>
                    </ul>
                </div>
                <div class="col-6 pe-1 ps-1">
                    <ul class="list-unstyled">
                        <li>
                            <a href="#">Security</a>
                        </li>
                        <li>
                            <a href="#">Billing</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row mb-1 ms-0 me-0">
                <div class="col-12 p-1 mb-2 pt-2">
                    <div class="text-extra-small text-primary">APPLICATION</div>
                </div>
                <div class="col-6 ps-1 pe-1">
                    <ul class="list-unstyled">
                        <li>
                            <a href="#">Themes</a>
                        </li>
                        <li>
                            <a href="#">Language</a>
                        </li>
                    </ul>
                </div>
                <div class="col-6 pe-1 ps-1">
                    <ul class="list-unstyled">
                        <li>
                            <a href="#">Devices</a>
                        </li>
                        <li>
                            <a href="#">Storage</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row mb-1 ms-0 me-0">
                <div class="col-12 p-1 mb-3 pt-3">
                    <div class="separator-light"></div>
                </div>
                <div class="col-6 ps-1 pe-1">
                    <ul class="list-unstyled">
                        <li>
                            <a href="#">
                                <i data-acorn-icon="help" class="me-2" data-acorn-size="17"></i>
                                <span class="align-middle">Help</span>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <i data-acorn-icon="file-text" class="me-2" data-acorn-size="17"></i>
                                <span class="align-middle">Docs</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-6 pe-1 ps-1">
                    <ul class="list-unstyled">
                        <li>
                            <a href="#">
                                <i data-acorn-icon="gear" class="me-2" data-acorn-size="17"></i>
                                <span class="align-middle">Settings</span>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <i data-acorn-icon="logout" class="me-2" data-acorn-size="17"></i>
                                <span class="align-middle">Logout</span>
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
        <li class="list-inline-item">
            <a href="#" data-bs-toggle="dropdown" data-bs-target="#notifications" aria-haspopup="true" aria-expanded="false" class="notification-button">
                <div class="position-relative d-inline-flex">
                    <i data-acorn-icon="bell" data-acorn-size="18"></i>
                    <span class="position-absolute notification-dot rounded-xl"></span>
                </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end wide notification-dropdown scroll-out" id="notifications">
                <div class="scroll">
                    <ul class="list-unstyled border-last-none">
                        <li class="mb-3 pb-3 border-bottom border-separator-light d-flex">
                            <img src="{{asset('backend/img/profile/profile-1.webp')}}" class="me-3 sw-4 sh-4 rounded-xl align-self-center" alt="..." />
                            <div class="align-self-center">
                                <a href="#">Joisse Kaycee just sent a new comment!</a>
                            </div>
                        </li>
                        <li class="mb-3 pb-3 border-bottom border-separator-light d-flex">
                            <img src="{{asset('backend/img/profile/profile-2.webp')}}" class="me-3 sw-4 sh-4 rounded-xl align-self-center" alt="..." />
                            <div class="align-self-center">
                                <a href="#">New order received! It is total $147,20.</a>
                            </div>
                        </li>
                        <li class="mb-3 pb-3 border-bottom border-separator-light d-flex">
                            <img src="{{asset('backend/img/profile/profile-3.webp')}}" class="me-3 sw-4 sh-4 rounded-xl align-self-center" alt="..." />
                            <div class="align-self-center">
                                <a href="#">3 items just added to wish list by a user!</a>
                            </div>
                        </li>
                        <li class="pb-3 pb-3 border-bottom border-separator-light d-flex">
                            <img src="{{asset('backend/img/profile/profile-6.webp')}}" class="me-3 sw-4 sh-4 rounded-xl align-self-center" alt="..." />
                            <div class="align-self-center">
                                <a href="#">Kirby Peters just sent a new message!</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </li>
    </ul>
    <!-- Icons Menu End -->

    <!-- Menu Start -->
    <div class="menu-container flex-grow-1">
        <ul id="menu" class="menu">
            @can('admin.menu')
            <li>
                <a href="#adminMenu" data-href="/adminMenu">
                    <i data-acorn-icon="power" class="icon" data-acorn-size="18"></i>
                    <span class="label">Admin</span>
                </a>
                <ul id="adminMenu">
                    @can('user.list')
                    <li>
                        <a href="{{route('admin.user.list')}}">
                            <span class="label">Əməkdaşlar</span>
                        </a>
                    </li>
                    @endcan

                    <li>
                        @can('role.perm.menu')
                        <a href="#role_perms" data-href="/adminFinance">
                            <span class="label">Rol və icazələr</span>
                        </a>
                        @endcan
                        <ul id="role_perms">
                            @can('role.list')
                                <li>
                                    <a href="{{route('admin.role.list')}}">
                                        <span class="label">Rollar</span>
                                    </a>
                                </li>
                            @endcan
                            @can('permission.list')
                                <li>
                                    <a href="{{route('admin.permission.list')}}">
                                        <span class="label">İcazə səhifələri</span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                    @can('system.sms')
                    <li>
                        <a href="{{ route('admin.sms-template.index') }}">
                            <span class="label">SMS şablonları</span>
                        </a>
                    </li>
                    @endcan
                    @can('system.settings')
                        <li>
                            <a href="{{ route('admin.settings.index') }}">
                                <span class="label">Ayarlar</span>
                            </a>
                        </li>
                    @endcan
                    @can('credit.menu')
                    <li>
                        <a href="#credit_menu" data-href="/admin/credit">
                            <span class="label">Kredit</span>
                        </a>
                        <ul id="credit_menu">
                            <li>
                                <a href="{{ route('admin.credit.periods') }}">
                                    <span class="label">Faizlər</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.credit.terms') }}">
                                    <span class="label">Şərtlər və qaydalar</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endcan
                </ul>
            </li>
            @endcan
            <li>
                <a href="#whmenu" data-href="/perfume">
                    <i data-acorn-icon="gift" class="icon" data-acorn-size="18"></i>
                    <span class="label">Məhsullar</span>
                </a>
                <ul id="whmenu">
                    @can('products.menu')
                    <li>
                        <a href="{{route('admin.product.list')}}">
                            <span class="label">Məhsullar</span>
                        </a>
                    </li>
                    @endcan
                    @can('product.review')
                        <li>
                            <a href="{{ route('admin.product.review.list') }}">
                                <span class="label">Rəylər</span>
                            </a>
                        </li>
                    @endcan
                    @can('category.menu')
                        <li>
                            <a href="{{route('admin.category.list')}}">
                                <span class="label">Kateqoriyalar</span>
                            </a>
                        </li>
                    @endcan
                    @can('brands.menu')
                        <li>
                            <a href="{{route('admin.brand.list')}}">
                                <span class="label">Brendlər</span>
                            </a>
                        </li>
                    @endcan
                    @can('size.menu')
                    <li>
                        <a href="{{route('admin.size.list')}}">
                            <span class="label">Ölçülər</span>
                        </a>
                    </li>
                    @endcan
                    @can('type.menu')
                    <li>
                        <a href="{{route('admin.type.list')}}">
                            <span class="label">Növlər</span>
                        </a>
                    </li>
                    @endcan
                    @can('ingredient.menu')
                        <li>
                            <a href="{{route('admin.ingredient.list')}}">
                                <span class="label">Ingredientlər</span>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>

            @can('crm')
            <li>
                <a href="{{ route('admin.crm.index') }}">
                    <i data-acorn-icon="user" class="icon" data-acorn-size="18"></i>
                    <span class="label">CRM</span>
                </a>
            </li>
            @endcan

            @can('site.menu')
            <li>
                <a href="#site-parameters" data-href="/site-parameters">
                    <i data-acorn-icon="boxes" class="icon" data-acorn-size="18"></i>
                    <span class="label">Sayt</span>
                </a>
                <ul id="site-parameters">
                    @can('site.banners')
                    <li>
                        <a href="{{route('admin.banner.list')}}">
                            <span class="label">Bannerlər</span>
                        </a>
                    </li>
                    @endcan
                    @can('site.faq')
                        <li>
                            <a href="{{route('admin.faq.list')}}">
                                <span class="label">FAQ</span>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
            @endcan
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
