<?php

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\TypeFromTable;
use BlackParadise\LaravelAdmin\Core\ValidationManager;
use BlackParadise\LaravelAdmin\Core\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbstractController extends Controller
{
    /**
     * @param Model $model
     * @param Request $request
     * @return Application|Factory|View
     */
    public function index(Model $model, Request $request)
    {
        return (new DashboardPresenter())->getTablePage($request->get('entity_name'), $request);
    }

    /**
     * @param Model $model
     * @param Request $request
     * @return Application|Factory|View
     */
    public function create(Model $model, Request $request)
    {
        return (new DashboardPresenter())->getCreatePage($request->get('entity_name'), $model);
    }

    public function store(Model $model, Request $request)
    {
        $name = $request->get('entity_name');
        if(config('bpadmin.dashboard.entities')[$name]['validation_type'] === 'default') {
            $vars = (new TypeFromTable())->getTypeList($model);
            $data = (new ValidationManager)->validate(
                $vars,
                $request,
                $name
            );
            $model::create($data);
            return redirect('/admin/'.$name);
        } else {
            $data = $request->validate(config('bpadmin.dashboard.entities')[$name]['store_rules']);
            config('bpadmin.dashboard.entities')[$name]['entity']::create($data);
            return redirect('/admin/'.$name);
        }
    }

    public function show(Model $model)
    {
        $name = request()->get('entity_name');
        if(config('bpadmin.dashboard.entities')[$name]['type'] === 'default') {
            return (new DashboardPresenter())->getShowPage(
                config('bpadmin.dashboard.entities')[$name]['show_title'],
                $model,
                $name
            );
        } else {
            dd('not-default');
        }
    }

    public function edit(Model $model)
    {
        $name = request()->get('entity_name');
        if(config('bpadmin.dashboard.entities')[$name]['type'] === 'default') {
            $vars = (new TypeFromTable())->getTypeList($model);
            return (new DashboardPresenter())->getEditPage(
                $model,
                $name,
                $vars,
                array_key_exists('options',config('bbpadmin.dashboard.entities')[$name]) ?
                    config('bpadmin.dashboard.entities')[$name]['options']
                    :
                    []
            );
        } else {
            dd('not-default');
        }
    }

    public function update(Model $model, Request $request)
    {
        $name = $request->get('entity_name');
        if(config('bpadmin.dashboard.entities')[$name]['validation_type'] === 'default') {
            $vars = (new TypeFromTable())->getTypeList($model);
            $data = (new ValidationManager)->validate(
                $vars,
                $request,
                $name,
                $model->toArray()
            );
            $model->update($data);
            return redirect('/admin/'.$name);
        } else {
            $data = $request->validate(config('bpadmin.dashboard.entities')[$name]['update_rules']);
            config('bpadmin.dashboard.entities')[$name]['entity']::create($data);
            return redirect('/admin/'.$name);
        }
    }

    public function destroy(Model $model)
    {
        $name = request()->get('entity_name');
        if(config('bpadmin.dashboard.entities')[$name]['type'] === 'default') {
            $model->delete();
            return redirect('/admin/'.$name);
        } else {
            dd('not-default');
        }
    }
}
