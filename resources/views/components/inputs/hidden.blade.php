<input type="hidden"
    @foreach($attributes as $key => $value)
    {!! $key.'="'.$value.'"' !!}
    @endforeach
>
