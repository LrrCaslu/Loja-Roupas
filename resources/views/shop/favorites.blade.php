@extends('layouts.shop')
@section('title','Meus favoritos | Aurora')
@section('content')<div class="container page-shell"><div class="page-heading"><span class="eyebrow">PEÇAS ESPECIAIS</span><h1>Seus favoritos ♡</h1></div>@if($products->isEmpty())<div class="empty-state"><p>Nenhum favorito por enquanto.</p><a href="{{ route('catalog') }}" class="btn btn-dark">Explorar peças</a></div>@else<div class="products-grid">@foreach($products as $product)@include('partials.product-card')@endforeach</div>{{ $products->links() }}@endif</div>@endsection
