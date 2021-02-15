
@foreach($fields as $key => $value)
    @if($value !== 'boolean')
        <div class="form-group">
            <label for="{{$key}}" class="form-label">{{trans($name.'.'.$key)}}</label>
            @if($value === 'select')
                @include('bpadmin::components.inputs.'.$value,[
                    'name'  =>  $key,
                    'value' =>  array_key_exists('choose',$options) && array_key_exists($key,$options['choose'])?$options['choose'][$key]:old($key),
                    'items' =>  $options[$key],
                    ])
            @else
                @include('bpadmin::components.inputs.'.$value,[
                    'name'  =>  $key,
                    'value' =>  old($key),
                    ])
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
                @include('bpadmin::components.inputs.'.$value,[
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
