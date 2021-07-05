
@foreach($fields as $key => $value)
    @if($value['type'] !== 'boolean')
        <div class="form-group">
            <label for="{{$key}}" class="form-label">{{trans('bpadmin::'.$name.'.'.$key)}}</label>
            @if($key === 'email')
                @include('bpadmin::components.inputs.email',[
                   'name'  =>  $key,
                   'value' =>  old($key),
                   'required' => $value['required'],
                   ])
            @elseif($key === 'password')
                @include('bpadmin::components.inputs.password',[
                'name'  =>  $key,
                'value' =>  old($key),
                'required' => $value['required'],
                ])
            @else
                @if(array_key_exists('relation',$value))
                    @include('bpadmin::components.inputs.select',[
                        'name'  =>  $key,
                        'value' =>  old($key),
                        'items' => $value['relation'],
                        'required' => $value['required'],
                        ])
                @else
                    @include('bpadmin::components.inputs.'.$value['type'],[
                        'name'  =>  $key,
                        'value' =>  old($key),
                        'required' => $value['required'],
                        ])
                @endif
            @endif
            @error($key)
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>
    @else
        <div class="form-group">
            <div class="custom-control custom-checkbox">
                @include('bpadmin::components.inputs.'.$value['type'],[
                    'name'  =>  $key,
                    'value' =>  null,
                    ])
                <label class="custom-control-label" for="{{$key}}">{{str_replace('_', ' ', ucfirst($key))}}</label>
            </div>
            @error($key)
            <span class="invalid-feedback">
                {{ $message }}
            </span>
            @enderror
        </div>
    @endif
@endforeach
