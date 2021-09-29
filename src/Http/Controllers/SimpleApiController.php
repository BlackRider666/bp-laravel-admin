<?php


namespace BlackParadise\LaravelAdmin\Http\Controllers;


use BlackParadise\LaravelAdmin\Core\AbstractRepo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class SimpleApiController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
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
