<table class="table table-clean m-2">
    <tbody>
    @foreach($item as $key => $value)
        <tr>
            <th>{{$fields[$key]}}</th>
            @if($key === 'thumb_url')
               <td>
                   <img src="{{$value}}" alt="{{$key}}" class="img-thumbnail col-6">
               </td>
            @elseif($key === 'color')
                <td>
                    <input type="color" value="{{$value}}" disabled>
                </td>
            @else
            <td>{{array_key_exists($key,$relation)?$relation[$key]:$value}}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
