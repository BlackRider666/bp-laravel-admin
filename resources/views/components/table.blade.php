<div class="panel-content">
    <div class="frame-wrap">
        <table class="table m-0 table-hover">
            <thead class="bg-fusion-50">
            <tr>
                <th scope="col">#</th>
                @foreach($headers as $header)
                    <th scope="col">{{$header}}</th>
                @endforeach
                @if(!$withoutShow||!$withoutToolbar)
                    <th scope="col">{{trans('bpadmin::common.forms.options')}}</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <th scope="row">{{$item->id}}</th>
                    @foreach($headers as $header)
                        <td>{{$item->$header}}</td>
                    @endforeach
                    <td class="row">
                        @if(!$withoutShow)
                            <a class="col" href="{{url('admin/'.$name.'/'.$item->id)}}"><i class="fal fa-eye text-info"></i></a>
                        @endif
                        @if(!$withoutToolbar)
                        <a class="col" href="{{url('admin/'.$name.'/'.$item->id.'/edit')}}"><i class="fal fa-edit text-warning"></i></a>
                        <form class="col" action="{{url('admin/'.$name.'/'.$item->id)}}" method="POST">
                            {{method_field('DELETE')}}
                            @csrf
                            <button type="submit" class="border-0 bg-white">
                                <i class="fal fa-trash-alt text-danger"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="text-center">
            {{ $items->appends(request()->query())->links() }}
        </div>
    </div>
</div>
