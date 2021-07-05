<?php


namespace BlackParadise\LaravelAdmin\Core;

use Illuminate\Http\Request;

class ValidationManager
{
    public function validate(array $vars, Request $request, string $name, $entity = [])
    {
        $rules = [];
        foreach ($vars as $key => $value) {
            if($value['type'] === 'boolean') {
                $rules[$key][] = 'boolean';
            }
            if($value['type'] === 'date') {
                $rules[$key][] = 'date';
            }
            if($value['type'] === 'float') {
                $rules[$key][] = 'numeric';
            }
            if($value['type'] === 'image') {
                $rules[$key][] = 'image';
            }
            if($value['type'] === 'integer') {
                $rules[$key][] = 'integer';
            }
            if($key === 'password') {
                $rules[$key] = ['required', 'string', 'min:8'];
            }
            if($value['type'] === 'string') {
                if($key === 'email') {
                    if($entity) {
                        $rules[$key] = [
                            'string',
                            'email',
                            'unique:' . $name . ','
                            . $value.','
                            . $entity[config('bpadmin.entities')[$name]['key']].','
                            . config('bpadmin.entities')[$name]['key']
                        ];
                    } else {
                        $rules[$key] = ['string', 'email', 'unique:' . $name];
                    }
                } else {
                    $rules[$key] = ['string','max:255'];
                }
            }
            if($value['type'] === 'text') {
                $rules[$key][] = 'string';
            }
            if($value['required']) {
                $rules[$key][] = 'required';
            }
        }
        return $request->validate($rules);
    }
}
