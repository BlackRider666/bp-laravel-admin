<?php


namespace BlackParadise\LaravelAdmin\Core;


use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AbstractRepo
{
    /**
     * @var Model
     */
    private $model;

    public function __construct(string $model)
    {
        $this->model = app($model);
    }

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

    /**
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function search(array $data): LengthAwarePaginator
    {
        $perPage = array_key_exists('perPage',$data)?$data['perPage']:10;
        return $this->query()->paginate($perPage);
    }
}
