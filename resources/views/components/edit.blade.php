@foreach($fields as $key => $value)
    @if($value['type'] !== 'boolean')
        <div class="form-group">
            <label for="{{$key}}" class="form-label">{{trans('bpadmin::'.$name.'.'.$key)}}</label>
            @if($value['type'] === 'image')
                <?php $url = $key.'_url' ?>
                @include('bpadmin::components.inputs.'.$model->getCasts()[$field],[
                    'name'  =>  $key,
                    'value' =>  $model->$url,
                    ])
            @elseif($key === 'email')
                @include('bpadmin::components.inputs.email',[
                   'name'  =>  $key,
                   'value' =>  $model->$key,
                   'required' => $value['required'],
                   ])
            @elseif($key === 'password')
                @include('bpadmin::components.inputs.password',[
                'name'  =>  $key,
                'value' =>  $model->$key,
                'required' => $value['required'],
                ])
            @else
                @if(array_key_exists('relation',$value))
                    @include('bpadmin::components.inputs.select',[
                        'name'  =>  $key,
                        'value' =>  $model->$key,
                        'items' => $value['relation'],
                        'required' => $value['required'],
                        ])
                @else
                    @include('bpadmin::components.inputs.'.$value['type'],[
                        'name'  =>  $key,
                        'value' =>  $model->$key,
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
                    'value' =>  $model->$key,
                    ])
                <label class="custom-control-label" for="{{$key}}">{{str_replace('_', ' ', ucfirst($key))}}</label>
            </div>
            @error($key)
            <div class="invalid-feedback">
                        {{ $message }}
                    </div>
            @enderror
        </div>
    @endif
@endforeach
