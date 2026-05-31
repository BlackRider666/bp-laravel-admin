<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Http\Middleware\SetBPAdminLocale;
use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Persists the admin panel locale selection in a cookie.
 *
 * The actual application locale is applied on each request by
 * SetBPAdminLocale middleware, which reads the same cookie.
 */
final readonly class LocaleController
{
    public function __construct(
        private AvailableLocalesResolver $resolver,
    ) {}

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->resolver->list())],
        ]);

        return back()->withCookie(
            cookie()->forever(SetBPAdminLocale::COOKIE, $data['locale']),
        );
    }
}
