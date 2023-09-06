<?php

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\LaravelAdmin\Core\FormBuilder\Form;
use BlackParadise\LaravelAdmin\Core\FormBuilder\FormBuilder;
use BlackParadise\LaravelAdmin\Core\PageBuilder\Components\LinkComponent;
use BlackParadise\LaravelAdmin\Core\PageBuilder\PageBuilder;
use BlackParadise\LaravelAdmin\Core\TableBuilder\TableBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

class DashboardPresenter
{

    private FormBuilder $form;
    /**
     * @param string $name
     * @param Request $request
     * @return View
     */
    public function getTablePage(string $name, Request $request): View
    {
        $entityArray = config('bpadmin.entities')[$name];
        if($entityArray['type'] === 'default') {
            $data = $request->all(['perPage','page','sortBy','sortDesc', 'q']);
            $searchable = array_key_exists('search', $entityArray);
            $headers = $entityArray['table_headers'];
            $items = (new AbstractRepo($entityArray['entity'], $searchable?$entityArray['search']:null))->search($data, $headers);
            $items = $items->toArray();
            $entity = new $entityArray['entity'];
            $items['data'] = array_map(function ($item) use ($headers, $entity) {
                if ($entity->translatable) {
                    foreach(array_intersect($entity->translatable, $headers) as $transField)
                    {
                        $item[$transField] = $item[$transField]?$item[$transField][config('bpadmin.languages')[0]]:null;
                    }
                }
                return $item;
            },$items['data']);
            $headers[] = 'actions';
            return (new PageBuilder('bpadmin::layout.crud',ucfirst($name),[
                (new TableBuilder($headers,$items, $name, $searchable))->render(),
            ], [
                (new LinkComponent('Create','mdi-plus',[
                    'href'  => route('bpadmin.'.$name.'.create'),
                ]))->render()
            ]))->render();
        }

        if (!array_key_exists('index', $entityArray['custom_pages'])) {
            throw new RuntimeException('Add field "custom_pages" "index" of type PageBuilder to your config or change "type" to "default"');
        }

        return $entityArray['custom_pages']['index']->render();
    }

    /**
     * @param Model $item
     * @param string $name
     * @return View
     */
    public function getShowPage(Model $item, string $name): View
    {
        $entityArray = config('bpadmin.entities')[$name];
        if($entityArray['type'] === 'default') {
            $fieldsAll = Cache::rememberForever($name, function () use ($item) {
                return (new TypeFromTable())->getTypeList($item);
            });
            $showFields = array_flip($entityArray['show_fields']);
            $fields = array_intersect_key($fieldsAll, $showFields);
            return (new PageBuilder('bpadmin::layout.crud',$entityArray['show_title'],[
                view('bpadmin::components.show', [
                    'fields' => $fields,
                    'name'   => $name,
                    'item'   => $item,
                ])
                ]))->render();
        }

        if (!array_key_exists('show', $entityArray['custom_pages'])) {
            throw new RuntimeException('Add field "custom_pages" "show" of type PageBuilder to your config or change "type" to "default"');
        }

        return $entityArray['custom_pages']['show']->render();
    }

    /**
     * @param string $name
     * @param Model $model
     * @return View
     */
    public function getCreatePage(string $name, Model $model): View
    {
        $entityArray = config('bpadmin.entities')[$name];
        if($entityArray['type'] === 'default') {
            $this->form = (new FormBuilder(new Form([],$model,$name)));
            return (new PageBuilder('bpadmin::layout.crud','Create '.ucfirst($name),[
                $this->form->renderCreateForm(),
            ]))->render();
        }

        if (!array_key_exists('create', $entityArray['custom_pages'])) {
            throw new RuntimeException('Add field "custom_pages" "create" of type PageBuilder to your config or change "type" to "default"');
        }

        return $entityArray['custom_pages']['create']->render();

    }

    /**
     * @param Model $model
     * @param string $name
     * @return View
     */
    public function getEditPage(Model $model, string $name): View
    {
        $entityArray = config('bpadmin.entities')[$name];
        if($entityArray['type'] === 'default') {
            $fields = Cache::rememberForever($name, static function() use ($model) {
                return (new TypeFromTable())->getTypeList($model);
            });
            array_walk($fields,  static function(&$item, $key) use ($model) {
                $item['value'] = $model->$key;
            });
            $fields['submit'] = [
                'type' => 'submit',
                'label' => trans('bpadmin::common.forms.update')
            ];
            $this->form = (new FormBuilder(new Form([],$model,$name)));
            return (new PageBuilder('bpadmin::layout.crud','Update '.ucfirst($name),[
                $this->form->renderEditForm(),
            ]))->render();
        }
        if (!array_key_exists('edit', $entityArray['custom_pages'])) {
            throw new RuntimeException('Add field "custom_pages" "edit" of type PageBuilder to your config or change "type" to "default"');
        }

        return $entityArray['custom_pages']['edit']->render();
    }
}
