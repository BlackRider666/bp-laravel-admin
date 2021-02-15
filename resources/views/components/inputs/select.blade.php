<select class="form-control {{$errors->has($name) ? 'is-invalid':''}}" id="{{$name}}" name="{{$name}}" >
    <option value="" selected disabled hidden>Choose here</option>
    @foreach($items as $item)
        <option value="{{$item['id']}}" {{$value === $item['id']?'selected':''}}>{{isset($item['title'])?$item['title']:$item['title_ru']}}</option>
    @endforeach
</select>
