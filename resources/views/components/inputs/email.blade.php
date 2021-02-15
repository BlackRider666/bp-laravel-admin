<input type="email" class="form-control {{$errors->has($name) ? 'is-invalid':''}}" id="{{$name}}" name="{{$name}}" value="{{$value?:''}}">
