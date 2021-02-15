@extends('bpadmin::main')
@section('title', ucfirst($name))
@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-hdr">
                    <h2>
                        {{ucfirst($name)}}
                    </h2>
                    @if(!$withoutCreate)
                    <div class="panel-toolbar">
                        <a class="btn btn-primary" href="{{url('admin/'.$name.'/create')}}"><i class="fal fa-plus"></i> {{trans('bpadmin::common.titles.create')}}</a>
                    </div>
                    @endif
                </div>
                <div class="panel-container show">
                    <form action="{{url('admin/'.$name)}}" method="GET" class="px-2 px-sm-3 pt-3">
                        <h3 class="mb-2">
                            {{$items->total() . ' ' . trans('bpadmin::common.forms.results_for') . ' ' . ucfirst($name)}}
                        </h3>
                        <div class="input-group shadow-1 rounded">
                            <input type="text" name="search" class="form-control shadow-inset-2" id="filter-icon" aria-label="type 2 or more letters" placeholder="{{trans('bpadmin::common.forms.search_placeholder')}}" value="{{request()->get('search')}}">
                            <div class="input-group-append">
                                <button class="btn btn-primary hidden-sm-down waves-effect waves-themed" type="submit"><i class="fal fa-search mr-lg-2"></i><span class="hidden-md-down">{{trans('bpadmin::common.forms.search')}}</span></button>
                            </div>
                        </div>
                    </form>
                    @include('bpadmin::components.table',[
                            'headers'   =>  $headers,
                            'name'  => $name,
                            'items' => $items,
                            'withoutToolbar'    =>  $withoutToolbar,
                            'options'           =>  $options,
                            'withoutShow'       =>  $withoutShow
                        ])
                </div>
            </div>

        </div>
    </div>
@endsection
