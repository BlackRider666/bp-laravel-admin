<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

trait HandlesEntityAuthorization
{
    /**
     * @param string $ability
     * @param $entity
     * @return bool
     * @throws AuthorizationException
     */
    protected function authorizeAction(string $ability, $entity = null): bool
    {
        $user = auth()->user();
        $entityClass = $entity ?? $this->BPModel->model; // Отримуємо клас або об’єкт

        if (Gate::getPolicyFor($entityClass)) {
            if ($user->cannot($ability, $entityClass)) {
                throw new AuthorizationException("You do not have permission to {$ability} this entity.");
            }
        }

        return true;
    }
}
