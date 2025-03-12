<?php

namespace BlackParadise\LaravelAdmin\Core\Presenters;

use Exception;
use Inertia\Response;
use Illuminate\View\View;
use BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\PageFactory;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\FormFactory;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\EmailInputFactory;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\PasswordInputFactory;

class AuthPresenter
{
    /**
     * @return Response|View
     * @throws Exception
     */
    public function getLoginPage(): Response|View
    {
        $form = FormFactory::make([
            'action'    =>  route('bpadmin.auth.loginPost'),
            'method'    =>  'POST',
            'submit_label' => __('bpadmin::auth.btn.login')
        ]);
        $form->addField(EmailInputFactory::make(['name' => 'email', 'required' => true],'auth',[]));
        $form->addField(PasswordInputFactory::make(['name' => 'password', 'required' => true],'auth',[]));

        $page = PageFactory::make('auth',config('bpadmin.title'),[
            $form->render(),
        ]);

        return $page->render();
    }
}
