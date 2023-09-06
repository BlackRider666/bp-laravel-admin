<translatable-input {!!  count($errors) > 0 ? 'error="'.$errors[0].'"':null!!}
    @foreach($attributes as $key => $value)
        @if($key !== 'value')
            {!! $key.'="'.$value.'"' !!}
        @else
            @if($value)
                {!! ":".$key."='".json_encode($value)."'" !!}
            @else
                {!! ":".$key."='".json_encode(bpadmin_object_translatable_field(config('bpadmin.languages')))."'" !!}
            @endif
        @endif
    @endforeach
    :languages='{{json_encode(config('bpadmin.languages'))}}'
></translatable-input>
