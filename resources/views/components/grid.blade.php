<div class="panel-content">
    <div class="frame-wrap">
        <div class="card-columns">
            @foreach($items as $item)
                <div class="card" style="max-width: 18rem;">
                    <img src="{{$item->thumb_url}}" class="card-img-top" alt="...">
                    <div class="card-body">
                        <form class="col" action="{{route('admin.'.$name.'.destroy',$item->id)}}" method="POST">
                            {{method_field('DELETE')}}
                            @csrf
                            <button type="submit" class="border-0 bg-white">
                                <i class="fal fa-trash-alt text-danger"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-center">
            {{ $items->appends(request()->query())->links() }}
        </div>
    </div>
</div>
