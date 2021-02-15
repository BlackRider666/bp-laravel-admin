<?php

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\ValidationManager;
use BlackParadise\LaravelAdmin\Core\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbstractController extends Controller
{
    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    public function index(Request $request)
    {
        return (new DashboardPresenter())->getTablePage(request()->get('entity_name'), $request);
    }

    /**
     * @return Application|Factory|View
     */
    public function create()
    {
        return (new DashboardPresenter())->getCreatePage(request()->get('entity_name'));
    }

    public function store(Request $request)
    {
        $name = $request->get('entity_name');
        if(config('bpadmin.entities')[$name]['validation_type'] === 'default') {
            $data = (new ValidationManager)->validate(
                config('bpadmin.entities')[$name]['variables'],
                $request,
                $name
            );
            config('bpadmin.entities')[$name]['entity']::create($data);
            return redirect('/admin/'.$name);
        } else {
            $data = $request->validate(config('bpadmin.entities')[$name]['store_rules']);
            config('bpadmin.entities')[$name]['entity']::create($data);
            return redirect('/admin/'.$name);
        }
    }

    public function show(int $id)
    {
        $name = request()->get('entity_name');
        if(config('bpadmin.entities')[$name]['type'] === 'default') {
            $entity = config('bpadmin.entities')[$name]['entity']::find($id);
            return (new DashboardPresenter())->getShowPage(
                config('bpadmin.entities')[$name]['show_title'],
                $entity,
                $name
            );
        } else {
            dd('not-default');
        }
    }

    public function edit(int $id)
    {
        $name = request()->get('entity_name');
        if(config('bpadmin.entities')[$name]['type'] === 'default') {
            $entity = config('bpadmin.entities')[$name]['entity']::find($id);
            return (new DashboardPresenter())->getEditPage(
                $entity,
                $name,
                config('bpadmin.entities')[$name]['variables'],
                array_key_exists('options',config('bpadmin.entities')[$name]) ?
                    config('bpadmin.entities')[$name]['options']
                    :
                    []
            );
        } else {
            dd('not-default');
        }
    }

    public function update(int $id, Request $request)
    {
        $name = $request->get('entity_name');
        if(config('bpadmin.entities')[$name]['validation_type'] === 'default') {
            $entity = config('bpadmin.entities')[$name]['entity']::find($id);
            $data = (new ValidationManager)->validate(
                config('bpadmin.entities')[$name]['variables'],
                $request,
                $name,
                $entity->toArray()
            );
            $entity->update($data);
            return redirect('/admin/'.$name);
        } else {
            $data = $request->validate(config('bpadmin.entities')[$name]['update_rules']);
            config('bpadmin.entities')[$name]['entity']::create($data);
            return redirect('/admin/'.$name);
        }
    }

    public function destroy(int $id)
    {
        $name = request()->get('entity_name');
        if(config('bpadmin.entities')[$name]['type'] === 'default') {
            $entities = config('bpadmin.entities')[$name]['entity']::destroy($id);
            return redirect('/admin/'.$name);
        } else {
            dd('not-default');
        }
    }
}
