<select class="form-control {{$errors->has($name) ? 'is-invalid':''}}" id="{{$name}}" name="{{$name}}" {{$multiple ? 'multiple': ''}}>
    <option value="" {{!$value?'selected':''}} disabled hidden>Choose here</option>
    @foreach($items as $key => $val)
        <option value="{{$key}}" {{$value === $key?'selected':''}}>{{$val}}</option>
    @endforeach
</select>
