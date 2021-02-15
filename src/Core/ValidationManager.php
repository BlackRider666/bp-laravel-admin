<?php


namespace BlackParadise\Admin\Core;

use Illuminate\Http\Request;

class ValidationManager
{
    public function validate(array $vars, Request $request, string $name, $entity = [])
    {
        $rules = [];
        foreach ($vars as $key => $value) {
            if($value === 'boolean') {
                $rules[$key] = ['required','boolean'];
            }
            if($value === 'date') {
                $rules[$key] = ['required','date'];
            }
            if($value === 'email') {
                if($entity) {
                    $rules[$key] = [
                        'required',
                        'string',
                        'email',
                        'unique:' . $name . ','
                        . $value.','
                        . $entity[config('bpadmin.entities')[$name]['key']].','
                        . config('bpadmin.entities')[$name]['key']
                    ];
                } else {
                    $rules[$key] = ['required', 'string', 'email', 'unique:' . $name];
                }
                //dd($rules);
            }
            if($value === 'float') {
                $rules[$key] = ['required','numeric'];
            }
            if($value === 'image') {
                $rules[$key] = ['required','image'];
            }
            if($value === 'integer') {
                $rules[$key] = ['required','integer'];
            }
            if($value === 'password') {
                $rules[$key] = ['required', 'string', 'min:8'];
            }
            if($value === 'phone') {
                $rules[$key] = ['required','string','max:255'];
            }
            if($value === 'string') {
                $rules[$key] = ['required','string','max:255'];
            }
            if($value === 'text') {
                $rules[$key] = ['required','string'];
            }
        }
        return $request->validate($rules);
    }
}
