@extends('layouts.shop')
@section('title','Página não encontrada | Aurora')
@section('content')<div class="container empty-state" style="min-height:55vh"><span class="eyebrow">ERRO 404</span><h1>Esta página saiu de coleção.</h1><p>Mas temos muitas outras peças esperando por você.</p><a class="btn btn-dark" href="{{ route('home') }}">Voltar à loja</a></div>@endsection
