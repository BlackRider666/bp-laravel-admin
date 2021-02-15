@extends('bpadmin::main')
@section('title', trans('bpadmin::common.titles.edit') . ' ' . ucfirst(substr($name, 0, -1)))
@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-hdr">
                    <h2>
                        {{trans('bpadmin::common.titles.edit') . ' ' . ucfirst(substr($name, 0, -1))}}
                    </h2>
                </div>
                <div class="panel-container show">
                    <div class="panel-content">
                        <form action="{{url('admin/'.$name.'/'.$model->getKey())}}" method="POST" enctype="multipart/form-data">
                            @csrf
                            {{method_field('PUT')}}
                            @include('bpadmin::components.edit',[
                                'model'     =>  $model,
                                'fields'    =>  $fields,
                                'options'   =>  $options,
                            ])
                            @include('bpadmin::components.inputs.submit')
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
