@extends('layouts.app', ['title' => 'Forget Password'])

@section('content')
<main>

    <section class="no-border-bottom section-sm">
        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-sm-6 col-sm-offset-3 col-lg-4 col-lg-offset-4">
                    <div class="card">
                        <div class="card-header">
                            <h6>Password recovery</h6>
                        </div>

                        <div class="card-block">
                            @if (session('status'))
                                <p class="alert alert-success">{{ session('status') }}</p>
                            @endif
                            <br>
                            <form role="form" method="POST" action="{{ url('/password/email') }}">
                                {{ csrf_field() }}
                                <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                                    <input class="form-control input-lg" name="email" type="text" placeholder="Email address">
                                    @if ($errors->has('email'))
                                        <span class="help-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                    @endif
                                </div>

                                <button class="btn btn-primary btn-block" type="submit">Send Password Reset Link</button>
                            </form>
                            <br>
                        </div>

                        <div class="card-footer">
                            <div class="row text-center">
                                <div class="col-xs-6">
                                    <a href="{{ url('login') }}">Login</a>
                                </div>

                                <div class="col-xs-6">
                                    <a href="{{ url('register') }}">Register</a>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </section>

</main>
<!-- END Main container -->

@endsection
