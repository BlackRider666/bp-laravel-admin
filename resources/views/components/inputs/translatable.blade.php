<translatable-input {!!  count($errors) > 0 ? 'error="'.$errors[0].'"':null!!}
    {{dd($attributes)}}
    @foreach($attributes as $key => $value)
        @if($key !== 'value')
            {!! $key.'="'.$value.'"' !!}
        @else
            {!! $key.'="'.($value?json_encode($value):json_encode('{}')).'"' !!}
        @endif
    @endforeach
    :languanges='{{json_encode(config('bpadmin.languages'))}}'
/>
