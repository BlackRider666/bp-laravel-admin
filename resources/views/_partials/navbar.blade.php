<aside class="page-sidebar">
    <div class="page-logo">
        <a href="#" class="page-logo-link press-scale-down d-flex align-items-center position-relative" data-toggle="modal" data-target="#modal-shortcut">
            <span class="page-logo-text mr-1">{{config('bpadmin.title')}}</span>
            <span class="position-absolute text-white opacity-50 small pos-top pos-right mr-2 mt-n2"></span>
            <i class="fal fa-angle-down d-inline-block ml-1 fs-lg color-primary-300"></i>
        </a>
    </div>
    <nav id="js-primary-nav" class="primary-nav" role="navigation">
        <div class="nav-filter">
            <div class="position-relative">
                <input type="text" id="nav_filter_input" placeholder="Filter menu" class="form-control" tabindex="0">
                <a href="#" onclick="return false;" class="btn-primary btn-search-close js-waves-off" data-action="toggle" data-class="list-filter-active" data-target=".page-sidebar">
                    <i class="fal fa-chevron-up"></i>
                </a>
            </div>
        </div>
        @if(auth()->check())
        <div class="info-card">
            <img src="{{Auth::user()->avatar_url}}" class="profile-image rounded-circle" alt="Dr. Codex Lantern" style="height: 3rem">
            <div class="info-card-text">
                <a href="#" class="d-flex align-items-center text-white">
                    <a href="#" class="d-flex align-items-center text-white">
                                    <span class="text-truncate text-truncate-sm d-inline-block">
                                        {{ Auth::user()->full_name}}
                                    </span>
                    </a>
                </a>
            </div>
            <a href="#" onclick="return false;" class="pull-trigger-btn" data-action="toggle" data-class="list-filter-active" data-target=".page-sidebar" data-focus="nav_filter_input">
                <i class="fal fa-angle-down"></i>
            </a>
        </div>
        @endif
        <ul id="js-nav-menu" class="nav-menu">
            <li class="nav-title">Dashboard</li>
            <li class="{{request()->is('admin')?'active':''}}">
                <a href="{{route('bpadmin.pages.index')}}" title="Dashboard">
                    <i class="fal fa-chart-line"></i>
                    <span class="nav-link-text">Main</span>
                </a>
            </li>
            @foreach(config('bpadmin.dashboard.menu') as $key => $entity)
                <?php $patterns = [];
                    foreach($entity['items'] as $it) {
                        $patterns[] = 'admin/'.$it.'/*';
                        $patterns[] = 'admin/'.$it;
                    }
                ?>
                <li class="{{request()->is($patterns)?'active':''}}">
                    <a href="#" title="{{ucwords(str_replace('_',' ',$key))}}" data-filter-tags="{{str_replace('_',' ',$key)}}">
                        <i class="fal {{$entity['icon']}}"></i>
                        <span class="nav-link-text">{{ucwords(str_replace('_',' ',$key))}}</span>
                    </a>
                    <ul>
                        @foreach($entity['items'] as $item)
                            <li class="{{request()->is('admin/'.$item.'/*')|| request()->is('admin/'.$item)?'active':''}}">
                                <a href="{{url('/admin/'.$item)}}" title="{{ucwords(str_replace('_',' ',$item))}}" data-filter-tags="{{str_replace('_',' ',$item)}}" class=" waves-effect waves-themed">
                                    <span class="nav-link-text">{{ucwords(str_replace('_',' ',$item))}}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </nav>
</aside>
