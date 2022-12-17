<?php

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EntityExistMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $urlArray = explode("/", $request->getRequestUri());
        $name = explode('?',$urlArray[2])[0];
        $id = array_key_exists(3,$urlArray)?(int)$urlArray[3]:0;
        if(array_key_exists($name,config('bpadmin.entities'))) {
            $request->merge([
                'entity_name'  =>  $name,
            ]);
            $entity = config('bpadmin.entities')[$name]['entity'];
            app()->bind('Illuminate\Database\Eloquent\Model', function() use($entity,$id){
                if ($id !== 0) {
                    return (new $entity())->find($id);
                }
                return new $entity();
            });
            return $next($request);
        } else {
            abort(404,'Page not found!');
        }
    }
}
