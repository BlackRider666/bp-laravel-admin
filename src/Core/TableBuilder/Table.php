<?php

namespace BlackParadise\LaravelAdmin\Core\TableBuilder;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class Table
{

    private array $headers = [];

    private $items;

    private string $name;
    private bool $searchable;

    public function __construct(array $headers, string $name, bool $searchable = false)
    {

        $this->headers = array_map( static function($header) use ($name) {
            $item['value'] = $header;
            $item['text'] = trans('bpadmin::'.$name.'.'.$header);
            $item['sortable'] = $header !== 'actions';
            return $item;
        },$headers);
        $this->name = $name;
        $this->searchable = $searchable;
    }

    public function render(array $options = [])
    {
        return view('bpadmin::components.table', [
            'headers'   =>  $this->headers,
            'name'  => $this->name,
            'items' => $this->items,
            'withoutToolbar'    =>  false,
            'options'           =>  $options,
            'withoutShow'       =>  false,
            'searchable'        =>  $this->searchable
        ])->render();
    }

    /**
     * @param LengthAwarePaginator $items
     */
    public function setItems($items): void
    {
        $this->items = $items;
    }
}
