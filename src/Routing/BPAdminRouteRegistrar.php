<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Routing;

use BlackParadise\LaravelAdmin\Http\Controllers\AdminAuthController;
use BlackParadise\LaravelAdmin\Http\Controllers\AdminDashboardController;
use BlackParadise\LaravelAdmin\Http\Controllers\AdminEntityController;
use BlackParadise\LaravelAdmin\Http\Controllers\LocaleController;
use BlackParadise\LaravelAdmin\Http\Controllers\SafeFileDownloadController;
use BlackParadise\LaravelAdmin\Http\Middleware\AdminAuthenticated;
use BlackParadise\LaravelAdmin\Http\Middleware\ResolveEntityKey;
use BlackParadise\LaravelAdmin\Http\Middleware\SetBPAdminLocale;
use BlackParadise\LaravelAdmin\Http\Middleware\ValidateEntity;
use Illuminate\Routing\Router;

/**
 * Registers all BPAdmin routes.
 *
 * Called from DashboardServiceProvider::boot() instead of loadRoutesFrom().
 * Centralises route configuration so new route groups (e.g. custom entity
 * actions) can be added here without touching multiple files.
 */
final readonly class BPAdminRouteRegistrar
{
    /** @var array<string> */
    private array $baseMiddleware;
    /** @var array<string> */
    private array $authenticatedMiddleware;

    /**
     * @param array<string> $baseMiddleware
     */
    public function __construct(
        private Router $router,
        private string $prefix,
        array $baseMiddleware,
    ) {
        $this->baseMiddleware = array_merge(
            $baseMiddleware,
            [SetBPAdminLocale::class],
        );
        $this->authenticatedMiddleware = array_merge(
            $this->baseMiddleware,
            [AdminAuthenticated::class],
        );
    }

    public function register(): void
    {
        $this->router->prefix($this->prefix)->name('bpadmin.')->group(function (): void {
            $this->registerAuth();
            $this->registerDashboard();
            $this->registerLocale();
            $this->registerFiles();
            $this->registerEntity();
        });
    }

    private function registerLocale(): void
    {
        // Locale switching is only meaningful for authenticated admin sessions;
        // anonymous users have no admin UI to render in another locale.
        $this->router
            ->post('/locale', [LocaleController::class, 'switch'])
            ->middleware($this->authenticatedMiddleware)
            ->name('locale.switch');
    }

    private function registerAuth(): void
    {
        $this->router
            ->prefix('auth')
            ->middleware($this->baseMiddleware)
            ->name('auth.')
            ->group(function (): void {
                $this->router->get('/login', [AdminAuthController::class, 'showLoginForm'])
                    ->name('login');
                // Brute-force protection: 5 attempts per minute per ip+email.
                $this->router->post('/login', [AdminAuthController::class, 'login'])
                    ->middleware('throttle:5,1')
                    ->name('login.post');
                $this->router->post('/logout', [AdminAuthController::class, 'logout'])
                    ->name('logout');
            });
    }

    private function registerDashboard(): void
    {
        $this->router
            ->get('/', [AdminDashboardController::class, 'index'])
            ->middleware($this->authenticatedMiddleware)
            ->name('dashboard');
    }

    private function registerFiles(): void
    {
        $this->router
            ->get('/files/download/{disk}/{path}', [SafeFileDownloadController::class, 'download'])
            ->where('path', '.*')
            ->middleware($this->authenticatedMiddleware)
            ->name('files.download');
    }

    private function registerEntity(): void
    {
        $entityMiddleware = array_merge(
            $this->authenticatedMiddleware,
            [ValidateEntity::class, ResolveEntityKey::class],
        );

        $idPattern = '[a-zA-Z0-9_-]+';

        $this->router
            ->prefix('{entity}')
            ->middleware($entityMiddleware)
            ->name('entity.')
            ->group(function () use ($idPattern): void {
                // Collection routes (no {id})
                $this->router->get('/', [AdminEntityController::class, 'index'])->name('index');
                $this->router->get('/create', [AdminEntityController::class, 'create'])->name('create');
                $this->router->post('/', [AdminEntityController::class, 'store'])->name('store');
                $this->router->post('/bulk-destroy', [AdminEntityController::class, 'bulkDestroy'])->name('bulk-destroy');
                $this->router->post('/actions/{action}', [AdminEntityController::class, 'action'])->name('action');

                // Record routes (with {id})
                $this->router->get('/{id}', [AdminEntityController::class, 'show'])->name('show')->where('id', $idPattern);
                $this->router->get('/{id}/edit', [AdminEntityController::class, 'edit'])->name('edit')->where('id', $idPattern);
                $this->router->put('/{id}', [AdminEntityController::class, 'update'])->name('update')->where('id', $idPattern);
                $this->router->patch('/{id}', [AdminEntityController::class, 'update'])->name('update.patch')->where('id', $idPattern);
                $this->router->delete('/{id}', [AdminEntityController::class, 'destroy'])->name('destroy')->where('id', $idPattern);
                $this->router->post('/{id}/actions/{action}', [AdminEntityController::class, 'action'])->name('action.row')->where('id', $idPattern);
            });
    }
}
