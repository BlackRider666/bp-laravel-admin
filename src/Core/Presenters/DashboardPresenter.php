<?php

namespace BlackParadise\LaravelAdmin\Core\Presenters;

use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Form;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\FormBuilder;
use BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\Components\LinkComponent;
use BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\PageBuilder;
use BlackParadise\LaravelAdmin\Core\Builders\TableBuilder\TableBuilder;
use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPresenter
{
    /**
     * @param array $headers
     * @param array $items
     * @param string $name
     * @param bool $searchable
     * @return View
     */
    public function getTablePage(array $headers, array $items, string $name, bool $searchable = false): View
    {
        $headers[] = 'actions';
        return (new PageBuilder('bpadmin::layout.crud',ucfirst($name),[
            (new TableBuilder($headers,$items, $name, $searchable))->render(),
        ], [
            (new LinkComponent('Create','mdi-plus',[
                'href'  => route('bpadmin.'.$name.'.create'),
            ]))->render()
        ]))->render();
    }

    public function getCreatePage(BPModel $BPModel): View
    {
        $form = new FormBuilder(new Form([],new $BPModel->model,$BPModel));
        return (new PageBuilder('bpadmin::layout.crud','Create '.ucfirst($BPModel->name),[
            $form->renderCreateForm(),
        ]))->render();
    }

    /**
     * @param Model $item
     * @param array $fields
     * @param string $name
     * @return View
     */
    public function getShowPage(Model $item, array $fields, string $name): View
    {

        return (new PageBuilder('bpadmin::layout.crud',ucfirst($name).' '.$item->getKey(),[
            view('bpadmin::components.show', [
                'fields' => array_flip($fields),
                'name'   => $name,
                'item'   => $item,
            ])
        ]))->render();
    }

    /**
     * @param BPModel $BPModel
     * @param Model $model
     * @return View
     */
    public function getEditPage(BPModel $BPModel,Model $model): View
    {
        $form = (new FormBuilder(new Form([],$model,$BPModel)));
        return (new PageBuilder('bpadmin::layout.crud','Update '.ucfirst($BPModel->name),[
            $form->renderEditForm(),
        ]))->render();
    }
}
