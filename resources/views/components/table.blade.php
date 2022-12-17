<crud-items
    :headers="{{json_encode($headers)}}"
    :items="{{json_encode($items) }}"
    :routes="{{json_encode([
    'index' => route('bpadmin.'.$name.'.index'),
    'show' => route('bpadmin.'.$name.'.show', ':id'),
    'edit' => route('bpadmin.'.$name.'.edit', ':id'),
    'delete' => route('bpadmin.'.$name.'.destroy', ':id'),
    ])}}"
    csrftoken="{{csrf_token()}}"
    searchable="{{$searchable}}"
    oldsearch="{{request()->query('q')?:''}}"
    sortby="{{request()->query('sortBy')}}"
    sortdesc="{{request()->query('sortDesc')}}"
></crud-items>
