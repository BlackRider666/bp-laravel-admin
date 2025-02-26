<?php

namespace BlackParadise\LaravelAdmin\Http\Actions\Entity;

use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\Interface\IndexEntityInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class IndexEntityAction implements IndexEntityInterface
{
    private BPModel $BPModel;
    private DashboardPresenter $dashboardPresenter;

    public function __construct(BPModel $BPModel, DashboardPresenter $dashboardPresenter)
    {
        $this->BPModel = $BPModel;
        $this->dashboardPresenter = $dashboardPresenter;
    }

    /**
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        $data = $request->all();
        $headers = $this->BPModel->tableHeaderFields;
        $searchable = !empty($this->BPModel->searchFields);

        $items = $this->BPModel->indexQuery($data,$headers)->toArray();
        $items['data'] = $this->prepareData($items['data'], $headers, new $this->BPModel->model);

        return $this->dashboardPresenter->getTablePage($headers, $items, $this->BPModel->name, $searchable);
    }

    private function prepareData(array $data, array $headers, Model $entity): array
    {
        if ($entity->translatable) {
            return array_map(static function ($item) use ($headers, $entity) {
                foreach(array_intersect($entity->translatable, $headers) as $transField)
                {
                    $item[$transField] = $item[$transField]?$item[$transField][App::currentLocale()]:null;
                }
            },$data);
        }

        return $data;
    }
}
