<header class="page-header" role="banner">
    <div class="page-logo">
        <a href="#" class="page-logo-link press-scale-down d-flex align-items-center position-relative" data-toggle="modal" data-target="#modal-shortcut">
            <span class="page-logo-text mr-1">{{config('bpadmin.title')}}</span>
            <span class="position-absolute text-white opacity-50 small pos-top pos-right mr-2 mt-n2"></span>
            <i class="fal fa-angle-down d-inline-block ml-1 fs-lg color-primary-300"></i>
        </a>
    </div>
    <div class="hidden-md-down dropdown-icon-menu position-relative">
        <a href="#" class="header-btn btn js-waves-off" data-action="toggle" data-class="nav-function-minify" title="Minify Navigation">
            <i class="ni ni-minify-nav"></i>
        </a>
    </div>
    <div class="hidden-lg-up">
        <a href="#" class="header-btn btn press-scale-down" data-action="toggle" data-class="mobile-nav-on">
            <i class="ni ni-menu"></i>
        </a>
    </div>
    @if(auth()->check())
    <div class="ml-auto d-flex">
        <div>
            <a href="#" data-toggle="dropdown" title="drlantern@gotbootstrap.com" class="header-icon d-flex align-items-center justify-content-center ml-2">
                <span class="ml-1 mr-1 text-truncate text-truncate-header hidden-xs-down"><i class="fal fa-user"></i> </span>
                <i class="ni ni-chevron-down hidden-xs-down"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-animated dropdown-lg">
                <div class="dropdown-header bg-trans-gradient d-flex flex-row py-4 rounded-top">
                    <div class="d-flex flex-row align-items-center mt-1 mb-1 color-white">
                        <div class="info-card-text">
                            <div class="fs-lg text-truncate text-truncate-lg">{{ Auth::user()->first_name.' '.Auth::user()->last_name}} </div>
                            <span class="text-truncate text-truncate-md opacity-80">{{ Auth::user()->email}} </span>
                        </div>
                    </div>
                </div>
                <div class="dropdown-divider m-0"></div>
                <a href="#" class="dropdown-item" data-toggle="modal" data-target=".js-modal-settings">
                    <span data-i18n="drpdwn.settings">Settings</span>
                </a>
                <div class="dropdown-divider m-0"></div>
                <a href="#" class="dropdown-item" data-action="app-fullscreen">
                    <span data-i18n="drpdwn.fullscreen">Fullscreen</span>
                    <i class="float-right text-muted fw-n">F11</i>
                </a>
                <div class="dropdown-divider m-0"></div>
                <a class="dropdown-item fw-500 pt-3 pb-3" href="{{ route('logout') }}"
                   onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                    <span data-i18n="drpdwn.page-logout">{{ __('Logout') }}</span>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </div>
        </div>
    </div>
    @endif
</header>
