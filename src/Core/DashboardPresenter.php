<?php


namespace BlackParadise\Admin\Core;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardPresenter
{
    public function getTablePage(
        array $headers,
        string $name,
        LengthAwarePaginator $items,
        bool $withoutToolbar = false,
        array $options = [],
        bool $withoutCreate = false,
        bool $withoutShow = false
        )
    {
        return view('bpadmin::components.table-page',[
            'headers'   =>  $headers,
            'name'      =>  $name,
            'items'     =>  $items,
            'withoutToolbar'    =>  $withoutToolbar,
            'options'           =>  $options,
            'withoutCreate'     =>  $withoutCreate,
            'withoutShow'       =>  $withoutShow
        ]);
    }

    public function getShowPage(string $header, Model $item, string $name, array $relation =[])
    {
        return view('bpadmin::components.show-page',[
            'header'    =>  $header,
            'data'      =>  [
                'item' => $item->only(array_keys(config('bpadmin.entities')[$name]['variables'])),
                'fields' => trans($name),
            ],
            'relation'  =>  $relation
        ]);
    }

    public function getCreatePage(array $fields,string $name, array $options = [])
    {
        return view('bpadmin::components.create-page',[
            'fields'    =>  $fields,
            'name'      =>  $name,
            'options'   =>  $options,
        ]);
    }

    public function getEditPage(Model $model,string $name,array $fields, array $options = [])
    {
        return view('bpadmin::components.edit-page',[
            'model'  =>  $model,
            'name'   =>  $name,
            'fields' =>  $fields,
            'options'   =>  $options,
        ]);
    }
}
