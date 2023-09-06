<?php

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\FormBuilder\Form;
use BlackParadise\LaravelAdmin\Core\StorageManager;
use BlackParadise\LaravelAdmin\Core\TypeFromTable;
use BlackParadise\LaravelAdmin\Core\ValidationManager;
use BlackParadise\LaravelAdmin\Core\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AbstractController extends Controller
{
    /**
     * @var DashboardPresenter
     */
    private DashboardPresenter $dashboardPresenter;

    public function __construct(DashboardPresenter $dashboardPresenter)
    {

        $this->dashboardPresenter = $dashboardPresenter;
    }

    /**
     * @param Model $model
     * @param Request $request
     * @return View
     */
    public function index(Model $model, Request $request): View
    {
        return $this->dashboardPresenter->getTablePage($request->get('entity_name'), $request);
    }

    /**
     * @param Model $model
     * @param Request $request
     * @return View
     */
    public function create(Model $model, Request $request): View
    {
        return $this->dashboardPresenter->getCreatePage($request->get('entity_name'), $model);
    }

    /**
     * @param Model $model
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Model $model, Request $request): RedirectResponse
    {
        $name = $request->get('entity_name');
        $entityArray = config('bpadmin.entities')[$name];

        if ($entityArray['type'] === 'custom') {
            return $entityArray['custom_store'];
        }
        $form = new Form([],$model,$name);
        $rules = array_key_exists('store_rules',$entityArray)? $entityArray['store_rules']:[];
        $validator = $form->validate($request->all(),$rules);

        if ($validator->fails()) {
            \Log::info($validator->errors()->messages());
            return redirect()->back()->withErrors($validator->errors()->messages())->withInput();
        }

        $data = $validator->validated();
        foreach ($request->allFiles() as $key => $value) {
            if (array_key_exists($key,$data)) {
                $data[$key] = (new StorageManager())
                    ->saveFile($value,$name.'_'.$key);
            }
        }

        $createdModel = $model::create($data);
        $methods = array_filter($data, function($item,$key) {
                return substr($key, -6) === 'method' && $item;
        },1);
        $methodsWithCorrectItem = array_map(function($item) {
            $items = explode(',',$item);
            return array_map(static function($item){
                return (int)$item;
            },$items);
        },$methods);
        foreach ($methodsWithCorrectItem as $method => $value) {
            $modelMethod = substr($method, 0,-7);
            $createdModel->$modelMethod()->sync($value);
        }

        return redirect()->route('bpadmin.'.$name.'.index');
    }

    /**
     * @param Model $model
     * @return View
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function show(Model $model): View
    {
        return $this->dashboardPresenter->getShowPage($model, request()->get('entity_name'));
    }

    /**
     * @param Model $model
     * @param Request $request
     * @return View
     */
    public function edit(Model $model, Request $request): View
    {
        return $this->dashboardPresenter->getEditPage($model, $request->get('entity_name'));
    }

    /**
     * @param Model $model
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Model $model, Request $request): RedirectResponse
    {
        $name = $request->get('entity_name');
        $entityArray = config('bpadmin.entities')[$name];

        if ($entityArray['type'] === 'custom') {
            return $entityArray['custom_store'];
        }
        $form = new Form([],$model,$name);
        $rules = array_key_exists('update_rules',$entityArray)? $entityArray['store_rules']:[];
        $validator = $form->validate($request->all(),$rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors()->messages())->withInput();
        }

        $data = $validator->validated();
        foreach ($request->allFiles() as $key => $value) {
            if (array_key_exists($key,$data)) {
                if ($model->$key !== null) {
                    (new StorageManager())->deleteFile($model->$key,$name.'_'.$key);
                }
                $data[$key] = (new StorageManager())
                    ->saveFile($value,$name.'_'.$key);
            }
        }

        $model->update($data);
        $methods = array_filter($data, function($item,$key) {
            return substr($key, -6) === 'method' && $item;
        },1);
        $methodsWithCorrectItem = array_map(function($item) {
            $items = explode(',',$item);
            return array_map(static function($item){
                return (int)$item;
            },$items);
        },$methods);
        foreach ($methodsWithCorrectItem as $method => $value) {
            $modelMethod = substr($method, 0,-7);
            $model->$modelMethod()->sync($value);
        }

        return redirect()->route('bpadmin.'.$name.'.index');
    }

    /**
     * @param Model $model
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function destroy(Model $model): RedirectResponse
    {
        $name = request()->get('entity_name');
        $entityArray = config('bpadmin.entities')[$name];

        if ($entityArray['type'] === 'custom') {
            return $entityArray['custom_destroy'];
        }

        $vars = Cache::rememberForever($name,function() use ($model) {
            return (new TypeFromTable())->getTypeList($model);
        });

        if ($items = array_filter($vars, function ($item) {
            return $item['type'] === 'file';
        })) {
            foreach($items as $key => $value) {
                if ($model->$key !== null) {
                    (new StorageManager())->deleteFile($model->$key,$name.'_'.$key);
                }
            }
        }
        $model->delete();

        return redirect()->route('bpadmin.'.$name.'.index');
    }
}
