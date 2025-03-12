<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Auth;

use BlackParadise\LaravelAdmin\Core\Presenters\AuthPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Auth\LoginPageActionInterface;
use Illuminate\View\View;
use Inertia\Response;

class LoginPageAction implements LoginPageActionInterface
{
    private AuthPresenter $authPresenter;

    public function __construct(AuthPresenter $authPresenter)
    {
        $this->authPresenter = $authPresenter;
    }

    public function __invoke(): View|Response
    {
        return $this->authPresenter->getLoginPage();
    }
}
