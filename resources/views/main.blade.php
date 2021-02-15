<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{config('bpadmin.title')}} | @yield('title')</title>
    <meta name="description" content="Page Titile">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no, minimal-ui">
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="msapplication-tap-highlight" content="no">
    @include('bpadmin::_partials.assets')
</head>
<body>
<div class="page-wrapper">
    <div class="page-inner">
        @include('bpadmin::_partials.navbar')
        <div class="page-content-wrapper">
            @include('bpadmin::_partials.header')
            <main id="js-page-content" role="main" class="page-content">
                @yield('content')
            </main>
            @include('bpadmin::_partials.footer')
        </div>
    </div>
</div>
@include('bpadmin::_partials.scripts')
</body>
</html>
