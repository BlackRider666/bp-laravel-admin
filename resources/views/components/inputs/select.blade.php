<?php
$items = array_map(function ($key, $value) {
    return [
        'value' => $key,
        'text' => $value,
    ];
},
    array_keys($items),
    array_values($items));
?>

<select-input {!!  count($errors) > 0 ? 'error="'.$errors[0].'"':null!!}
              @foreach($attributes as $key => $value)
                  @if($key === 'value')
                      {!! ':'.$key.'="'.json_encode($value).'"' !!}
                  @else
                      {!! $key.'="'.$value.'"' !!}
                  @endif
              @endforeach
              :items="{{json_encode($items)}}"
></select-input>
