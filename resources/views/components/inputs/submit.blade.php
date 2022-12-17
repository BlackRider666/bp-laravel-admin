<submit-input
    @foreach($attributes as $key => $value)
        {!! $key.'="'.$value.'"' !!}
    @endforeach
></submit-input>
