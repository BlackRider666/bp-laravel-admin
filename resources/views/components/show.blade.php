<table class="table table-clean m-2">
    <tbody>
    @foreach($fields as $key => $value)
        <tr>
            <th>{{trans('bpadmin::'.$name.'.'.$key)}}</th>
            @if($key === 'thumb_url')
               <td>
                   <img src="{{$value}}" alt="{{$key}}" class="img-thumbnail col-6">
               </td>
            @elseif($key === 'color')
                <td>
                    <input type="color" value="{{$value}}" disabled>
                </td>
            @else
                <td>{{$item->$key}}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
