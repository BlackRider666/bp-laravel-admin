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
                @if(array_key_exists('relation',$value))
                    <?php $method = substr($key,0,-3)?>
                    <td>{{$item->$method->relation_title}}</td>
                @else
                    <td>{{$item->$key}}</td>
                @endif
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
