<?php

namespace BlackParadise\Admin\CRUD\Http\Controllers;

use BlackParadise\Admin\Core\ValidationManager;
use BlackParadise\Admin\CRUD\Http\Controllers\Controller;
use BlackParadise\Admin\Core\DashboardPresenter;
use Illuminate\Http\Request;

class AbstractController extends Controller
{
    public function index(string $name)
    {
        if(array_key_exists($name,config('bpadmin.entities'))) {
            if(config('bpadmin.entities')[$name]['type'] === 'default') {
                $entities = config('bpadmin.entities')[$name]['entity']::paginate(
                    array_key_exists('paginate',config('bpadmin.entities')[$name])?
                        config('bpadmin.entities')[$name]['paginate']
                        :
                        10
                );
                return (new DashboardPresenter())->getTablePage(
                    config('bpadmin.entities')[$name]['table_headers'],
                    $name,
                    $entities
                );
            } else {
                dd('not-default');
            }
        } else {
            abort(404,'Page not found!');
        }
    }

    public function create(string $name)
    {
        if(array_key_exists($name,config('bpadmin.entities'))) {
            if(config('bpadmin.entities')[$name]['type'] === 'default') {
                return (new DashboardPresenter())->getCreatePage(
                    config('bpadmin.entities')[$name]['variables'],
                    $name,
                    array_key_exists('options',config('bpadmin.entities')[$name]) ?
                        config('bpadmin.entities')[$name]['options']
                        :
                        []
                );
            } else {
                dd('not-default');
            }
        } else {
            abort(404,'Page not found!');
        }
    }

    public function store(string $name, Request $request)
    {
        if(array_key_exists($name,config('bpadmin.entities'))) {
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
        } else {
            abort(404,'Page not found!');
        }
    }

    public function show(string $name,int $id)
    {
        if(array_key_exists($name,config('bpadmin.entities'))) {
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
        } else {
            abort(404,'Page not found!');
        }
    }

    public function edit(string $name,int $id)
    {
        if(array_key_exists($name,config('bpadmin.entities'))) {
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
        } else {
            abort(404,'Page not found!');
        }
    }

    public function update(string $name, int $id, Request $request)
    {
        if(array_key_exists($name,config('bpadmin.entities'))) {
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
        } else {
            abort(404,'Page not found!');
        }
    }

    public function destroy(string $name, int $id)
    {
        if(array_key_exists($name,config('bpadmin.entities'))) {
            if(config('bpadmin.entities')[$name]['type'] === 'default') {
                $entities = config('bpadmin.entities')[$name]['entity']::destroy($id);
                return redirect('/admin/'.$name);
            } else {
                dd('not-default');
            }
        } else {
            abort(404,'Page not found!');
        }
    }
}
