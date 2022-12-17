<?php

namespace BlackParadise\LaravelAdmin\Core\PageBuilder;
use Illuminate\View\View;

class PageBuilder
{
    private string $bladePath;

    private Page $page;

    public function __construct(string $bladePath, string $title, array $components, array $headers = [])
    {
        $this->bladePath = $bladePath;
        $this->page = new Page($title,$components, $headers);
    }

    /**
     * @return View
     */
    public function render(): View
    {
        return view($this->bladePath,[
            'page'  =>  $this->page->render(),
        ]);
    }
}
