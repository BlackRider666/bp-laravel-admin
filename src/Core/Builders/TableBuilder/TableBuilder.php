<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\TableBuilder;

class TableBuilder
{
    private Table $table;

    public function __construct(array $headers, $items, string $name, bool $searchable)
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
