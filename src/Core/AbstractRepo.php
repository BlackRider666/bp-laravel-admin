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
    private Model $model;
    private $searchField;

    public function __construct(string $model,  $searchField = null)
    {
        $this->model = app($model);
        $this->searchField = $searchField;
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
     * @param array $fields
     * @return LengthAwarePaginator
     */
    public function search(array $data, array $fields): LengthAwarePaginator
    {
        $perPage = $data['perPage']?:10;
        $sortBy = $data['sortBy']?:'id';
        $page = $data['page']?:1;
        $sortDesc = $data['sortDesc'] !== null;
        $fields[] = 'id';
        $query = $this->query();
        if ($data['q']) {
            if (is_array($this->searchField)) {
                $query->where(function($sub) use ($data){
                    foreach($this->searchField as $key => $field) {
                        if ($key === 0) {
                            $sub->where($field,'like','%'.$data['q'].'%');
                        } else {
                            $sub->orWhere($field, 'like', '%' . $data['q'] . '%');
                        }
                    }
                });
            } else {
                $query->where($this->searchField,'like','%'.$data['q'].'%');
            }
        }
        $query->orderBy($sortBy,$sortDesc?'desc':'asc');
        return $query->paginate($perPage,$fields,'page',$page);
    }
}
