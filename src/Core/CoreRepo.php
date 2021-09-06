<?php


namespace BlackParadise\LaravelAdmin\Core;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class CoreRepo
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * CoreRepository constructor.
     */
    public function __construct()
    {
        $this->model = app($this->getModelClass());
    }

    /**
     * @return string
     */
    abstract protected function getModelClass(): string;


    /**
     * @return Builder|Model
     */
    protected function query()
    {
        return $this->model->newModelQuery();
    }

    /**
     * @param int $id
     * @return Model|null
     */
    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @return Collection
     */
    public function all(): Collection
    {
        return $this->query()->get();
    }
}
