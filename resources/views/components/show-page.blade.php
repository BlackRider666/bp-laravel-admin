@extends('bpadmin::main')
@section('title', $header)
@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-hdr">
                    <h2>
                        {{$header}}
                    </h2>
                </div>
                <div class="panel-container show">
                    @include('bpadmin::components.show',[
                        'name'  =>  $name,
                        'item' => $item,
                        'fields' => $fields,
                    ])
                </div>
            </div>

        </div>
    </div>
@endsection
