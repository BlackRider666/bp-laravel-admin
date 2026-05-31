<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Application\UseCases\Entity\ResolveEmbeddedRelationsUseCase;

/**
 * Translate sentinel-prefixed i18n keys emitted by use cases.
 *
 * Use cases keep no Laravel coupling, so they emit translation KEYS prefixed
 * with {@see ResolveEmbeddedRelationsUseCase::I18N_SENTINEL}. This translator
 * strips the prefix and runs the key through Laravel's `__()`. Plain validator
 * messages (without the sentinel) pass through unchanged.
 */
final class I18nErrorTranslator
{
    /**
     * @param array<string, array<string>> $errors
     * @return array<string, array<string>>
     */
    public function translate(array $errors): array
    {
        $sentinel = ResolveEmbeddedRelationsUseCase::I18N_SENTINEL;
        $sentinelLen = strlen($sentinel);

        return array_map(
            fn(array $messages): array => array_map(
                function (string $message) use ($sentinel, $sentinelLen): string {
                    if (!str_starts_with($message, $sentinel)) {
                        return $message;
                    }
                    return __(substr($message, $sentinelLen));
                },
                $messages,
            ),
            $errors,
        );
    }
}
