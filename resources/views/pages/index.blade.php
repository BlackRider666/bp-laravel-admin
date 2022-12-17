@extends('bpadmin::main')
@section('title', trans('bpadmin::common.welcome.title'))
@section('content')
    <crud-layout>
        <span slot="title">{{trans('bpadmin::common.welcome.title')}}</span>
        {{trans('bpadmin::common.welcome.desc')}}
    </crud-layout>
@endsection
