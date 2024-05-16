<?php

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $urlArray = explode("/", $request->getRequestUri());
        $name = explode('?',$urlArray[2])[0];
        if(array_key_exists($name,config('bpadmin.entities'))) {
            $className = snakeToPascalCase($name);
            app()->bind('BlackParadise\LaravelAdmin\Http\Requests\StoreAbstractEntityRequest', function() use($className,$id){
                return new BlackParadise\LaravelAdmin\Http\Requests\StoreAbstractEntityRequest(new ("\App\BPAdmin\\".$className)());
            });
            app()->bind('BlackParadise\LaravelAdmin\Http\Requests\UpdateAbstractEntityRequest', function() use($className,$id){
                return new BlackParadise\LaravelAdmin\Http\Requests\UpdateAbstractEntityRequest(new ("\App\BPAdmin\\".$className)());
            });
            return $next($request);
        } else {
            abort(404,'Page not found!');
        }
    }
}
