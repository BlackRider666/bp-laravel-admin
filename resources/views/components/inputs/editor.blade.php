<editor-input {!!  count($errors) > 0 ? 'error="'.$errors[0].'"':null!!}
@foreach($attributes as $key => $value)
    {!! $key.'="'.$value.'"' !!}
    @endforeach
></editor-input>
