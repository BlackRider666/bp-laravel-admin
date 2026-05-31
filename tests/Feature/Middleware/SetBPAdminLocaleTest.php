<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Middleware;

use BlackParadise\LaravelAdmin\Http\Middleware\SetBPAdminLocale;
use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SetBPAdminLocaleTest extends TestCase
{
    public function test_sets_app_locale_when_cookie_value_is_allowed(): void
    {
        config(['bpadmin.locales' => ['en', 'uk']]);

        $this->app->forgetInstance(AvailableLocalesResolver::class);

        $middleware = $this->app->make(SetBPAdminLocale::class);

        $request = Request::create('/', 'GET');
        $request->cookies->set(SetBPAdminLocale::COOKIE, 'uk');

        $middleware->handle($request, fn(): ResponseFactory|Response => response('ok'));

        $this->assertSame('uk', app()->getLocale());
    }

    public function test_ignores_cookie_value_when_not_in_allowed_locales(): void
    {
        config(['bpadmin.locales' => ['en', 'uk']]);

        $this->app->forgetInstance(AvailableLocalesResolver::class);

        app()->setLocale('en');

        $middleware = $this->app->make(SetBPAdminLocale::class);

        $request = Request::create('/', 'GET');
        $request->cookies->set(SetBPAdminLocale::COOKIE, 'zz');

        $middleware->handle($request, fn(): ResponseFactory|Response => response('ok'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_noop_when_cookie_missing(): void
    {
        config(['bpadmin.locales' => ['en', 'uk']]);

        $this->app->forgetInstance(AvailableLocalesResolver::class);

        app()->setLocale('en');

        $middleware = $this->app->make(SetBPAdminLocale::class);

        $request = Request::create('/', 'GET');

        $middleware->handle($request, fn(): ResponseFactory|Response => response('ok'));

        $this->assertSame('en', app()->getLocale());
    }
}
