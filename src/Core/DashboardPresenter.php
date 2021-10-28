<?php

namespace BlackParadise\LaravelAdmin\Core;

use Doctrine\DBAL\Schema\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardPresenter
{
    public function getTablePage(string $name, Request $request)
    {
        $entityArray = config('bpadmin.dashboard.entities')[$name];
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
        $fieldsAll = Cache::rememberForever($name,function() use ($item) {
            return (new TypeFromTable())->getTypeList($item);
        });
        $showFields = array_flip(config('bpadmin.dashboard.entities')[$name]['show_fields']);
        $fields = array_intersect_key($fieldsAll,$showFields);
        return view('bpadmin::components.show-page',[
            'header'    =>  $header,
            'name'      =>  $name,
            'item'      => $item,
            'fields'    => $fields,
        ]);
    }

    public function getCreatePage(string $name, Model $model)
    {
        $entityArray = config('bpadmin.dashboard.entities')[$name];
        if($entityArray['type'] === 'default') {
            $options = array_key_exists('options',$entityArray) ?
                $entityArray['options']
                :
                [];
            $fields = Cache::rememberForever($name,function() use ($model) {
                return (new TypeFromTable())->getTypeList($model);
            });

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
