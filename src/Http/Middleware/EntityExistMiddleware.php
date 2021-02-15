<?php

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EntityExistMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $name = explode("/", $request->getRequestUri())[2];
        if(array_key_exists($name,config('bpadmin.entities'))) {
            $request->merge([
                'entity_name'  =>  $name,
            ]);
            return $next($request);
        } else {
            abort(404,'Page not found!');
        }
    }
}
