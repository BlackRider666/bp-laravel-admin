<?php

namespace BlackParadise\LaravelAdmin\Core\Presenters;

use Exception;
use Inertia\Response;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\PageFactory;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\FormFactory;
use BlackParadise\LaravelAdmin\Core\Builders\TableBuilder\TableFactory;
use BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\Components\LinkFactory;

class DashboardPresenter
{
    /**
     * @param array $headers
     * @param array $items
     * @param string $name
     * @param bool $searchable
     * @return Response|View
     * @throws Exception
     */
    public function getTablePage(array $headers, array $items, string $name, bool $searchable = false): Response|View
    {
        $routes = $this->prepareRoutes($name);

        if (!empty(array_intersect(['show', 'edit', 'delete'], array_keys($routes)))) {
            $headers[] = 'actions';
        }

        $page = PageFactory::make(
            'crud',
            __('bpadmin::common.headers.table_entity',['entity' => __('bpadmin::'.$name.'.name')]),
            [
                TableFactory::make($headers, $items, $name, $searchable, $routes)->render(),
            ],
            array_filter([
                isset($routes['create']) ? LinkFactory::make(__('bpadmin::common.forms.create'), 'mdi-plus', [
                    'href' => route('bpadmin.' . $name . '.create'),
                ])->render() : null,
            ])
        );

        return $page->render();
    }

    /**
     * @param BPModel $BPModel
     * @return Response|View
     * @throws Exception
     */
    public function getCreatePage(BPModel $BPModel): Response|View
    {
        $form = FormFactory::make([],new $BPModel->model,$BPModel);
        return PageFactory::make('crud',__('bpadmin::common.headers.create_entity',['entity' => __('bpadmin::'.$BPModel->name.'.__name')]),[
            $form->renderCreateForm(),
        ])->render();
    }

    /**
     * @param Model $item
     * @param array $fields
     * @param string $name
     * @return Response|View
     * @throws Exception
     */
    public function getShowPage(Model $item, array $fields, string $name): Response|View
    {
        $data = array_map(static function ($field) use ($item) {
            return ['key' => $field, 'value' => $item->$field];
        }, $fields);

        return PageFactory::make('bpadmin::layout.crud',
            __('bpadmin::common.headers.show_entity',['entity' => __('bpadmin::'.$name.'.__name'), 'id' => $item->getKey()]),
            [
                TableFactory::make(['key', 'value'],$data,$name, false)
            ]
        )->render();
    }

    /**
     * @param BPModel $BPModel
     * @param Model $model
     * @return Response|View
     * @throws Exception
     */
    public function getEditPage(BPModel $BPModel,Model $model): Response|View
    {
        $form = FormFactory::make([],new $BPModel->model,$BPModel);
        return PageFactory::make('crud',
            __('bpadmin::common.headers.edit_entity',['entity' => __('bpadmin::'.$BPModel->name.'.__name'), 'id' => $model->getKey()]),[
            $form->renderEditForm(),
        ])->render();
    }

    /**
     * @param string $name
     * @return array
     */
    private function prepareRoutes(string $name): array
    {
        $entityClass = config("bpadmin.entities.$name");
        $routes = [];

        if (Gate::getPolicyFor($entityClass)) {
            if (Gate::allows('viewAny', $entityClass)) {
                $routes['index'] = route('bpadmin.' . $name . '.index');
            }
            if (Gate::allows('create', $entityClass)) {
                $routes['create'] = route('bpadmin.' . $name . '.create');
            }
            if (Gate::allows('view', $entityClass)) {
                $routes['show'] = route('bpadmin.' . $name . '.show', ':id');
            }
            if (Gate::allows('update', $entityClass)) {
                $routes['edit'] = route('bpadmin.' . $name . '.edit', ':id');
            }
            if (Gate::allows('delete', $entityClass)) {
                $routes['delete'] = route('bpadmin.' . $name . '.destroy', ':id');
            }
        } else {
            $routes = [
                'index'  => route('bpadmin.' . $name . '.index'),
                'create' => route('bpadmin.' . $name . '.create'),
                'show'   => route('bpadmin.' . $name . '.show', ':id'),
                'edit'   => route('bpadmin.' . $name . '.edit', ':id'),
                'delete' => route('bpadmin.' . $name . '.destroy', ':id'),
            ];
        }

        return $routes;
    }
}
