<?php

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use BlackParadise\LaravelAdmin\Core\Models\BPModel;

class EntityExistMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $urlArray = explode("/", $request->getRequestUri());
        $name = explode('?', $urlArray[2])[0];

        if (array_key_exists($name, config('bpadmin.entities'))) {
            $request->merge([
                'entity_name' => $name,
            ]);

            $className = snakeToPascalCase($name);
            $entityClass = "\App\BPAdmin\\$className";

            if (class_exists($entityClass)) {
                app()->bind(BPModel::class, function() use ($entityClass) {
                    return new $entityClass();
                });
            } else {
                abort(404, 'Entity class not found!');
            }

            return $next($request);
        }

        abort(404, 'Entity not found in configuration!');
    }
}
