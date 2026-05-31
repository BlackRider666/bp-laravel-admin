<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetBPAdminLocale
{
    public const COOKIE = 'bpadmin_locale';

    public function __construct(
        private AvailableLocalesResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->cookie(self::COOKIE);

        if (is_string($candidate) && in_array($candidate, $this->resolver->list(), true)) {
            app()->setLocale($candidate);
        }

        return $next($request);
    }
}
