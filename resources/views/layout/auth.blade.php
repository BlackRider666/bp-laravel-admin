@extends('bpadmin::main')
@section('title', $page['title'])
@section('content')
    <auth-layout>
        <span slot="title">{{$page['title']}}</span>
        {!! $page['html'] !!}
    </auth-layout>
@endsection
