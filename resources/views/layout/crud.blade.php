@extends('bpadmin::main')
@section('title', $page['title'])
@section('content')
    <crud-layout>
        <span slot="title">{{$page['title']}}</span>
        <div slot="title">{!! $page['headers'] !!}</div>
        {!! $page['html'] !!}
    </crud-layout>
@endsection
