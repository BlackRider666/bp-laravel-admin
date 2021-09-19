<?php


namespace BlackParadise\LaravelAdmin\Http\Controllers;


use BlackParadise\LaravelAdmin\Core\AbstractRepo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class SimpleApiController
{
    /**
     * @var Model
     */
    protected $repo;

    /**
     * CoreRepository constructor.
     */
    public function __construct()
    {
        $this->repo = new AbstractRepo($this->getModelClass());
    }

    /**
     * @return string
     */
    abstract protected function getModelClass(): string;


    public function index(Request $request)
    {
        return new JsonResponse($this->repo->search($request->all()));
    }
}
