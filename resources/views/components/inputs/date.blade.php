<input type="date" class="form-control {{$errors->has($name) ? 'is-invalid':''}}" id="{{$name}}" name="{{$name}}" value="{{$value?date('Y-m-d',strtotime($value)):now()->format('Y-m-d')}}">
