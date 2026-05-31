<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Views;

use BlackParadise\LaravelAdmin\Tests\TestCase;

final class LocaleRenderTest extends TestCase
{
    public function test_bundled_common_keys_resolve(): void
    {
        self::assertSame('Create', __('bpadmin::common.buttons.create'));
        self::assertSame('Previous', __('bpadmin::common.pagination.previous'));
        self::assertSame('Confirm deletion', __('bpadmin::common.modals.confirm_delete_title'));
    }

    public function test_bundled_auth_keys_resolve(): void
    {
        self::assertSame('Sign in', __('bpadmin::auth.login.title'));
        self::assertSame('Email', __('bpadmin::auth.login.email'));
    }

    public function test_bundled_validation_keys_resolve(): void
    {
        self::assertSame(
            'The email field is required.',
            __('bpadmin::validation.required', ['attribute' => 'email']),
        );
    }
}
