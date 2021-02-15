<div class="custom-file">
    <input type="file" class="custom-file-input {{$errors->has($name) ? 'is-invalid':''}}" id="{{$name}}" name="{{$name}}">
    <label class="custom-file-label" for="customFile">Choose file</label>
</div>
@if($value)
    <img src="{{$value}}" alt="{{$name}}" class="img-thumbnail col-6">
@endif
