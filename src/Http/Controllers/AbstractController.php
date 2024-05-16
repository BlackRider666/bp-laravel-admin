<?php

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\FormBuilder\Form;
use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use BlackParadise\LaravelAdmin\Core\StorageManager;
use BlackParadise\LaravelAdmin\Core\TypeFromTable;
use BlackParadise\LaravelAdmin\Core\ValidationManager;
use BlackParadise\LaravelAdmin\Core\DashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Controllers\Controller;
use BlackParadise\LaravelAdmin\Http\Requests\StoreAbstractEntityRequest;
use BlackParadise\LaravelAdmin\Http\Requests\UpdateAbstractEntityRequest;
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
     * @param BPModel $BPModel
     * @param Request $request
     * @return View
     */
    public function index(BPModel $BPModel, Request $request): View
    {
        return $BPModel->getTablePage($request);
    }

    /**
     * @param BPModel $BPModel
     * @param Request $request
     * @return View
     */
    public function create(BPModel $BPModel, Request $request): View
    {
        return $BPModel->getCreatePage();
    }

    /**
     * @param BPModel $BPModel
     * @param StoreAbstractEntityRequest $request
     * @return RedirectResponse
     */
    public function store(BPModel $BPModel, StoreAbstractEntityRequest $request): RedirectResponse
    {
        $BPModel->storeEntity($request->validated());

        return redirect()->route('bpadmin.'.$BPModel->name.'.index');
    }

    /**
     * @param BPModel $BPModel
     * @param int $id
     * @return View
     */
    public function show(BPModel $BPModel, int $id): View
    {
        return $BPModel->getShowPage($id);
    }

    /**
     * @param BPModel $BPModel
     * @param int $id
     * @return View
     */
    public function edit(BPModel $BPModel, int $id): View
    {
        return $BPModel->getEditPage($id);
    }

    /**
     * @param BPModel $BPModel
     * @param int $id
     * @param UpdateAbstractEntityRequest $request
     * @return RedirectResponse
     */
    public function update(BPModel $BPModel, int $id, UpdateAbstractEntityRequest $request): RedirectResponse
    {
        $BPModel->updateEntity($id, $request->validated());

        return redirect()->route('bpadmin.'.$BPModel->name.'.index');
    }

    /**
     * @param BPModel $BPModel
     * @return RedirectResponse
     */
    public function destroy(BPModel $BPModel, int $id): RedirectResponse
    {
        $BPModel->delete($id);

        return redirect()->route('bpadmin.'.$BPModel->name.'.index');
    }
}
