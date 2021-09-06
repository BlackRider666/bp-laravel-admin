@extends('bpadmin::main')
@section('title', trans('bpadmin::common.login_page.log_in'))
@section('content')
    <div class="row justify-content-center align-items-center">
        <div class="col-lg-6">
            <div class="blankpage-form-field">
                <div class="page-logo m-0 w-100 align-items-center justify-content-center rounded border-bottom-left-radius-0 border-bottom-right-radius-0 px-4">
                    <a href="javascript:void(0)" class="page-logo-link press-scale-down d-flex align-items-center">
                        <span class="page-logo-text mr-1">{{config('bpadmin.dashboard.title')}}</span>
                    </a>
                </div>
                <div class="card p-4 border-top-left-radius-0 border-top-right-radius-0">
                    <form action="{{route('bpadmin.loginPost')}}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="email">{{__('bpadmin::common.login_page.email')}}</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Email">
                            @error('email')
                                <span class="invalid-feedback" style="display:block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="password">{{__('bpadmin::common.login_page.password')}}</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password">
                            @error('password')
                                <span class="invalid-feedback" role="alert" style="display:block">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-default waves-effect waves-themed">{{__('bpadmin::common.login_page.log_in')}}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
