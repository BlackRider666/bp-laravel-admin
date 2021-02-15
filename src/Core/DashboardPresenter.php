<?php

namespace BlackParadise\LaravelAdmin\Core;

use Doctrine\DBAL\Schema\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DashboardPresenter
{
    public function getTablePage(string $name, Request $request)
    {
        $entityArray = config('bpadmin.entities')[$name];
        if($entityArray['type'] === 'default') {
            $withoutToolbar = false;
            $options = [];
            $withoutCreate = false;
            $withoutShow = false;
            $data = $request->all(['perPage']);
            $data['perPage'] = array_key_exists('paginate',$entityArray)?
                $entityArray['paginate']
                :
                10;
            $items = (new AbstractRepo($entityArray['entity']))->search($data);
            return view('bpadmin::components.table-page',[
                'headers'   =>  $entityArray['table_headers'],
                'name'      =>  $name,
                'items'     =>  $items,
                'withoutToolbar'    =>  $withoutToolbar,
                'options'           =>  $options,
                'withoutCreate'     =>  $withoutCreate,
                'withoutShow'       =>  $withoutShow
            ]);
        } else {
            dd('not-default');
        }
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

    public function getCreatePage(string $name)
    {
        $entityArray = config('bpadmin.entities')[$name];
        if($entityArray['type'] === 'default') {
            $options = array_key_exists('options',$entityArray) ?
                $entityArray['options']
                :
                [];
            $model = app($entityArray['entity']);
            $columns = (new TypeFromTable())->getTypeList($model);
            $fields = [];
            foreach ($model->getFillable() as $modelType) {
                $fields[$modelType] = $columns[$modelType];
            }
            return view('bpadmin::components.create-page',[
                'fields'    =>  $fields,
                'name'      =>  $name,
                'options'   =>  $options,
            ]);
        } else {
            dd('not-default');
        }

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
