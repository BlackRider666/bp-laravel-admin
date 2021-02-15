
@foreach($fields as $key => $value)
    @if($value !== 'boolean')
        <div class="form-group">
            <label for="{{$key}}" class="form-label">{{str_replace('_', ' ', ucfirst($key))}}</label>
            @if($value === 'image')
                <?php $url = $key.'_url' ?>
                @include('bpadmin::components.inputs.'.$model->getCasts()[$field],[
                    'name'  =>  $key,
                    'value' =>  $model->$url,
                    ])
            @elseif($value === 'select')
                @include('bpadmin::components.inputs.'.$value,[
                    'name'  =>  $key,
                    'value' =>  $model->$key,
                    'items' =>  $options[$key],
                    ])
            @else
                @include('bpadmin::components.inputs.'.$value,[
                    'name'  =>  $key,
                    'value' =>  $model->$key,
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
