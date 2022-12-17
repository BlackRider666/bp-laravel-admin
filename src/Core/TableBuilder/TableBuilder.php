<?php

namespace BlackParadise\LaravelAdmin\Core\TableBuilder;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TableBuilder
{
    private Table $table;

    public function __construct(array $headers, LengthAwarePaginator $items, string $name, bool $searchable)
    {
        $this->table = new Table($headers, $name, $searchable);
        $this->table->setItems($items);
    }

    /**
     * @param array $options
     * @return string
     */
    public function render(array $options = [])
    {
        return $this->table->render($options);
    }
}
