@extends('bpadmin::main')
@section('title', trans('bpadmin::common.welcome.title'))
@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-hdr">
                    <h2>
                        {{trans('bpadmin::common.welcome.title')}}
                    </h2>
                </div>
                <div class="panel-container show">
                    <div class="panel-content">
                        <p>{{trans('bpadmin::common.welcome.desc')}}</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
