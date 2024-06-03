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
                @php
                    $parts = explode('.', $key);
                    $reference = $item;
                @endphp

                @foreach($parts as $part)
                    @if(is_object($reference) && isset($reference->$part))
                        @php
                            $reference = $reference->$part;
                        @endphp
                    @else
                        @php
                            $reference = null;
                        @endphp
                        @break
                    @endif
                @endforeach
                <td>{{$reference}}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
