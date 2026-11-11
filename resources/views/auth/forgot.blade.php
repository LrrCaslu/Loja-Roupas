@extends('layouts.shop')
@section('title','Recuperar senha | Aurora')
@section('content')<div class="auth-background"><section class="auth-card"><h1>Recuperar acesso</h1><p>Enviaremos um link para seu e-mail. No ambiente local, ele aparece no log do Laravel.</p><form method="post" action="{{ route('password.email') }}">@csrf<label>E-mail<input type="email" required name="email"></label><button class="btn btn-dark block">Enviar instruções</button></form><a class="auth-bottom" href="{{ route('login') }}">Voltar ao login</a></section></div>@endsection
