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
    public function find(int $id, array $fields = ['*']): ?Model
    {
        return $this->query()->find($id,$fields);
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
     */
    public function search(array $data, array $fields = ['*']): LengthAwarePaginator
    {
        $perPage = array_key_exists('perPage',$data) && $data['perPage']?$data['perPage']:10;
        $sortBy = array_key_exists('sortBy',$data) && $data['sortBy']?$data['sortBy']:'id';
        $page = array_key_exists('page',$data) && $data['page']?$data['page']:1;
        $sortDesc = array_key_exists('sortDesc',$data) && $data['sortDesc'] !== null;
        if (!in_array('id', $fields)){
            $fields[] = 'id';
        }
        $query = $this->query();
        if (array_key_exists('q',$data) && $data['q']) {
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
        $relations = array_filter(
            array_map(function($item) {
                if (count(explode('.',$item)) > 1) {
                    return str_replace('.',':id,',$item);
                }
            },$fields)
        );
        $fieldsWithKey = array_map(function($item) {
            $arrItem = explode('.',$item);
            if (count($arrItem) > 1) {
                return $arrItem[0] . '_id';
            }
            return $item;
        },$fields);

        return $query->with($relations)->paginate($perPage,$fieldsWithKey,'page',$page);
    }
}
