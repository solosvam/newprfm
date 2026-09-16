<div class="nav-content d-flex">
    <!-- Logo Start -->
    <div class="logo position-relative">
        <a href="/">
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
            <li>
                <a href="#adminMenu" data-href="/adminMenu">
                    <i data-acorn-icon="power" class="icon" data-acorn-size="18"></i>
                    <span class="label">Admin</span>
                </a>
                <ul id="adminMenu">
                    @can('admin.list')
                    <li>
                        <a href="{{route('admin.list')}}">
                            <span class="label">Əməkdaşlar</span>
                        </a>
                    </li>
                    @endcan
                    @can('courier.list')
                        <li>
                            <a href="{{route('courier.list')}}">
                                <span class="label">Kuryerlər</span>
                            </a>
                        </li>
                    @endcan
                    @can('role.list')
                    <li>
                        <a href="{{route('role.list')}}">
                            <span class="label">Rollar</span>
                        </a>
                    </li>
                    @endcan
                    @can('faq.list')
                        <li>
                            <a href="{{route('faq.list')}}">
                                <span class="label">FAQ</span>
                            </a>
                        </li>
                    @endcan
                    @can('banner.list')
                        <li>
                            <a href="{{route('banner.list')}}">
                                <span class="label">Bannerlər</span>
                            </a>
                        </li>
                    @endcan
                    @can('permission.list')
                    <li>
                        <a href="{{route('permission.list')}}">
                            <span class="label">İcazə səhifələri</span>
                        </a>
                    </li>
                    @endcan


                    <li>
                        <a href="#adminFinance" data-href="/adminFinance">
                            <span class="label">Sub</span>
                        </a>
                        <ul id="adminFinance">
                            <li>
                                <a href="/Interface/Components/Accordion">
                                    <span class="label">Sub1</span>
                                </a>
                            </li>
                        </ul>
                    </li>


                </ul>
            </li>
            <li>
                <a href="#whmenu" data-href="/perfume">
                    <i data-acorn-icon="gift" class="icon" data-acorn-size="18"></i>
                    <span class="label">Parfumeriya</span>
                </a>
                <ul id="whmenu">
                    @can('product.list')
                    <li>
                        <a href="{{route('product.list')}}">
                            <span class="label">Ətirlər</span>
                        </a>
                    </li>
                    @endcan
                    @can('category.list')
                        <li>
                            <a href="{{route('category.list')}}">
                                <span class="label">Kateqoriyalar</span>
                            </a>
                        </li>
                    @endcan
                    @can('brand.list')
                        <li>
                            <a href="{{route('brand.list')}}">
                                <span class="label">Brendlər</span>
                            </a>
                        </li>
                    @endcan
                    @can('size.list')
                    <li>
                        <a href="{{route('size.list')}}">
                            <span class="label">Ölçülər</span>
                        </a>
                    </li>
                    @endcan
                    @can('type.list')
                    <li>
                        <a href="{{route('type.list')}}">
                            <span class="label">Növlər</span>
                        </a>
                    </li>
                    @endcan
                    @can('ingredient.list')
                        <li>
                            <a href="{{route('ingredient.list')}}">
                                <span class="label">Ingredientlər</span>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
            <li>
                <a href="#ordersmenu" data-href="/ordersmenu">
                    <i data-acorn-icon="basket" class="icon" data-acorn-size="18"></i>
                    <span class="label">Sifariş</span>
                </a>
                <ul id="ordersmenu">
                    <li>
                        <a href="/Apps/Calendar">
                            <span class="label">Sifarişlər</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Apps/Chat">
                            <span class="label">Saytlar</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Apps/Contacts">
                            <span class="label">Səbəblər</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Apps/Mailbox">
                            <span class="label">Statistika</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="#foreignwh" data-href="/foreignwh">
                    <i data-acorn-icon="boxes" class="icon" data-acorn-size="18"></i>
                    <span class="label">Menyu</span>
                </a>
                <ul id="foreignwh">
                    <li>
                        <a href="/Apps/Calendar">
                            <span class="label">Paket ekleme</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Apps/Chat">
                            <span class="label">Eklenenler</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Apps/Contacts">
                            <span class="label">Raflar</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Apps/Mailbox">
                            <span class="label">Çuvallar</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Apps/Mailbox">
                            <span class="label">Çuvallar</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="#blocks" data-href="/Blocks">
                    <i data-acorn-icon="grid-5" class="icon" data-acorn-size="18"></i>
                    <span class="label">Menyu</span>
                </a>
                <ul id="blocks">
                    <li>
                        <a href="/Blocks/Cta">
                            <span class="label">Cta</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/Details">
                            <span class="label">Details</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/Gallery">
                            <span class="label">Gallery</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/Images">
                            <span class="label">Images</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/List">
                            <span class="label">List</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/Stats">
                            <span class="label">Stats</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/Steps">
                            <span class="label">Steps</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/TabularData">
                            <span class="label">Tabular Data</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/Thumbnails">
                            <span class="label">Thumbnails</span>
                        </a>
                    </li>
                    <li>
                        <a href="/Blocks/Users">
                            <span class="label">Users</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="mega">
                <a href="#interface" data-href="/Interface">
                    <i data-acorn-icon="pocket-knife" class="icon" data-acorn-size="18"></i>
                    <span class="label">Menyu</span>
                </a>
                <ul id="interface">
                    <li>
                        <a href="#interfaceComponents" data-href="/Interface/Components">
                            <span class="label">Components</span>
                        </a>
                        <ul id="interfaceComponents">
                            <li>
                                <a href="/Interface/Components/Accordion">
                                    <span class="label">Accordion</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Alerts">
                                    <span class="label">Alerts</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Badge">
                                    <span class="label">Badge</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Breadcrumb">
                                    <span class="label">Breadcrumb</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Buttons">
                                    <span class="label">Buttons</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/ButtonGroup">
                                    <span class="label">Button Group</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Card">
                                    <span class="label">Card</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/CloseButton">
                                    <span class="label">Close Button</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Collapse">
                                    <span class="label">Collapse</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Dropdowns">
                                    <span class="label">Dropdowns</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/ListGroup">
                                    <span class="label">List Group</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Modal">
                                    <span class="label">Modal</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Navs">
                                    <span class="label">Navs</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Offcanvas">
                                    <span class="label">Offcanvas</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Pagination">
                                    <span class="label">Pagination</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Popovers">
                                    <span class="label">Popovers</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Progress">
                                    <span class="label">Progress</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Spinners">
                                    <span class="label">Spinners</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Toasts">
                                    <span class="label">Toasts</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Components/Tooltips">
                                    <span class="label">Tooltips</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="#interfaceForms" data-href="/Interface/Forms">
                            <span class="label">Forms</span>
                        </a>
                        <ul id="interfaceForms">
                            <li>
                                <a href="/Interface/Forms/Layouts">
                                    <span class="label">Layouts</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Forms/Validation">
                                    <span class="label">Validation</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Forms/Wizard">
                                    <span class="label">Wizard</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Forms/InputGroup">
                                    <span class="label">Input Group</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Forms/InputMask">
                                    <span class="label">Input Mask</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Forms/GenericForms">
                                    <span class="label">Generic Forms</span>
                                </a>
                            </li>
                            <li>
                                <a href="#formControls" data-href="/Interface/Forms/Controls">
                                    <span class="label">Controls</span>
                                </a>
                                <ul id="formControls">
                                    <li>
                                        <a href="/Interface/Forms/Controls/Autocomplete">
                                            <span class="label">Autocomplete</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/CheckboxRadio">
                                            <span class="label">Checkbox-Radio</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/DatePicker">
                                            <span class="label">Date Picker</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/Dropzone">
                                            <span class="label">Dropzone</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/Editor">
                                            <span class="label">Editor</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/InputSpinner">
                                            <span class="label">Input Spinner</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/Rating">
                                            <span class="label">Rating</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/Select2">
                                            <span class="label">Select2</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/Slider">
                                            <span class="label">Slider</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/Tags">
                                            <span class="label">Tags</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Forms/Controls/TimePicker">
                                            <span class="label">Time Picker</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="#interfacePlugins" data-href="/Interface/Plugins">
                            <span class="label">Plugins</span>
                        </a>
                        <ul id="interfacePlugins">
                            <li>
                                <a href="/Interface/Plugins/Carousel">
                                    <span class="label">Carousel</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Charts">
                                    <span class="label">Charts</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Clamp">
                                    <span class="label">Clamp</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/ContextMenu">
                                    <span class="label">Context Menu</span>
                                </a>
                            </li>
                            <li>
                                <a href="#pluginsDatatables" data-href="/Interface/Plugins/Datatables">
                                    <span class="label">Datatables</span>
                                </a>
                                <ul id="pluginsDatatables">
                                    <li>
                                        <a href="/Interface/Plugins/Datatables/EditableRows">
                                            <span class="label">Editable Rows</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Plugins/Datatables/EditableBoxed">
                                            <span class="label">Editable Boxed</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Plugins/Datatables/RowsAjax">
                                            <span class="label">Ajax Data</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Plugins/Datatables/RowsServerSide">
                                            <span class="label">Server Side</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Plugins/Datatables/BoxedVariations">
                                            <span class="label">Boxed Variations</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Lightbox">
                                    <span class="label">Lightbox</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/List">
                                    <span class="label">List</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Maps">
                                    <span class="label">Maps</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Notify">
                                    <span class="label">Notify</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Player">
                                    <span class="label">Players</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Progress">
                                    <span class="label">Progress</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Scrollbar">
                                    <span class="label">Scrollbar</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Shortcuts">
                                    <span class="label">Shortcuts</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Plugins/Sortable">
                                    <span class="label">Sortable</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="#interfaceContent" data-href="/Interface/Content">
                            <span class="label">Content</span>
                        </a>
                        <ul id="interfaceContent">
                            <li>
                                <a href="#icons" data-href="/Interface/Content/Icons">
                                    <span class="label">Icons</span>
                                </a>
                                <ul id="icons">
                                    <li>
                                        <a href="/Interface/Content/Icons/AcornInterface">
                                            <span class="label">Acorn Interface</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Icons/AcornCommerce">
                                            <span class="label">Acorn Commerce</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Icons/AcornLearning">
                                            <span class="label">Acorn Learning</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Icons/AcornMedical">
                                            <span class="label">Acorn Medical</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Icons/BootstrapIcons">
                                            <span class="label">Bootstrap Icons</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <a href="/Interface/Content/Images">
                                    <span class="label">Images</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Content/Tables">
                                    <span class="label">Tables</span>
                                </a>
                            </li>
                            <li>
                                <a href="/Interface/Content/Typography">
                                    <span class="label">Typography</span>
                                </a>
                            </li>
                            <li>
                                <a href="#menuVarieties" data-href="/Interface/Content/Menu">
                                    <span class="label">Menu</span>
                                </a>
                                <ul id="menuVarieties">
                                    <li>
                                        <a href="/Interface/Content/Menu/HorizontalStandard">
                                            <span class="label">Horizontal</span>
                                        </a>
                                    </li>
                                    <li></li>
                                    <li>
                                        <a href="/Interface/Content/Menu/VerticalStandard">
                                            <span class="label">Vertical</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Menu/VerticalSemiHidden">
                                            <span class="label">Vertical Hidden</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Menu/VerticalNoSemiHidden">
                                            <span class="label">Vertical No Hidden</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Menu/MobileOnly">
                                            <span class="label">Mobile Only</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="/Interface/Content/Menu/Sidebar">
                                            <span class="label">Sidebar</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                </ul>
            </li>
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
