<input type="number" name="{{$name}}" id="{{$name}}" class="form-control {{$errors->has($name) ? 'is-invalid':''}}" value="{{$value?:''}}" step="0.01" min="0">
