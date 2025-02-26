<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

interface IndexEntityInterface
{
    public function __invoke(Request|FormRequest $request): View;
}
