<?php

declare(strict_types=1);

/*
 * Architecture rule for laravel-next (Infrastructure layer):
 *
 * - Не імпортує Presentation packages (BladeUI / Inertia UI).
 * - Controllers тонкі — не звертаються до Repository/Mutator напряму, тільки через Use Case.
 *
 * Per spec §4.1, Infrastructure може імпортувати Domain, Application, Illuminate, Symfony.
 * Заборонене — Presentation packages (вони залежать від нас, не навпаки).
 */

arch('laravel adapter does not depend on presentation packages')
    ->expect('BlackParadise\LaravelAdmin')
    ->not->toUse([
        'BlackParadise\LaravelAdminBladeUI',
        'BlackParadise\AdminInertiaUI',
    ]);

arch('http controllers do not directly use repositories or mutators')
    ->expect('BlackParadise\LaravelAdmin\Http\Controllers')
    ->not->toUse([
        'BlackParadise\CoreAdmin\Domain\Repositories',
        'BlackParadise\CoreAdmin\Domain\Mutators',
    ]);
