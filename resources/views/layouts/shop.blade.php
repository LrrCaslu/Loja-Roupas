<!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', $settings['seo_title'] ?? 'Aurora Multimarcas')</title>
<meta name="description" content="@yield('description', $settings['seo_description'] ?? 'Moda, estilo e curadoria premium.')">
<meta property="og:type" content="website"><meta property="og:title" content="@yield('title','Aurora Multimarcas')">
@if(!empty($settings['favicon']))<link rel="icon" href="{{ asset(ltrim($settings['favicon'],'/')) }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
<style>:root{--ink:{{ $settings['primary_color'] ?? '#171717' }};--accent:{{ $settings['accent_color'] ?? '#b49b84' }};}</style>
@if(file_exists(public_path('build/manifest.json'))) @vite(['resources/css/app.css','resources/js/app.js']) @else <link rel="stylesheet" href="{{ asset('assets/aurora.css') }}"> @endif
</head><body class="shop-site">
<div class="promo-bar"><span>{{ $settings['announcement'] ?? 'Frete grátis acima de R$ 299 • Troca fácil' }}</span><span class="promo-right">Atendimento personalizado &nbsp; · &nbsp; Brasil</span></div>
<header class="shop-header"><div class="shop-header-inner">
 <button class="icon-button mobile-only" type="button" id="mobile-menu" aria-label="Abrir menu">☰</button>
 <a href="{{ route('home') }}" class="wordmark">@if(!empty($settings['logo']))<img src="{{ asset(ltrim($settings['logo'],'/')) }}" alt="{{ $settings['store_name'] ?? 'Aurora' }}">@else<span>{{ $settings['store_name'] ?? 'AURORA' }}</span><small>{{ $settings['store_subtitle'] ?? 'MULTIMARCAS' }}</small>@endif</a>
 <nav class="shop-nav" id="shop-nav"><a href="{{ route('catalog') }}">Todos</a>@foreach($categories ?? [] as $c)<a href="{{ route('category',$c) }}">{{ $c->name }}</a>@endforeach</nav>
 <div class="header-actions"><form class="header-search" action="{{ route('catalog') }}" method="get"><input name="q" type="search" placeholder="Buscar peças..." value="{{ request('q') }}" autocomplete="off"><button title="Buscar" aria-label="Buscar">⌕</button></form><a class="icon-link hide-sm" href="{{ auth()->check()?route('favorites'):route('login') }}" title="Favoritos">♡</a><a class="icon-link" href="{{ auth()->check()?route('account'):route('login') }}" title="Minha conta">♙</a><a class="icon-link cart-link" href="{{ route('cart') }}" title="Carrinho">♧<span class="cart-dot">{{ auth()->check()?\App\Models\Cart::where('user_id',auth()->id())->first()?->items()->sum('quantity')??0:\App\Models\Cart::where('session_id',session()->getId())->first()?->items()->sum('quantity')??0 }}</span></a></div>
</div></header>
@if(session('success'))<div class="flash success" role="status">✓ {{ session('success') }} <button onclick="this.parentElement.remove()">×</button></div>@endif
@if($errors->any())<div class="flash error" role="alert">{{ $errors->first() }} <button onclick="this.parentElement.remove()">×</button></div>@endif
<main>@yield('content')</main>
<footer class="site-footer"><div class="container footer-grid"><div><a href="{{ route('home') }}" class="wordmark footer-logo"><span>{{ $settings['store_name'] ?? 'AURORA' }}</span><small>{{ $settings['store_subtitle'] ?? 'MULTIMARCAS' }}</small></a><p>Uma curadoria de peças essenciais e contemporâneas. Seu estilo, sua história.</p></div><div><h4>Explore</h4><a href="{{ route('catalog') }}">Todos os produtos</a><a href="{{ route('catalog',['sale'=>1]) }}">Promoções</a><a href="{{ route('page','sobre') }}">Nossa história</a></div><div><h4>Ajuda</h4><a href="{{ route('page','contato') }}">Contato</a><a href="{{ route('page','trocas') }}">Trocas e devoluções</a><a href="{{ route('page','privacidade') }}">Privacidade</a><a href="{{ route('page','termos') }}">Termos de uso</a></div><div><h4>Receba novidades</h4><p>Novas coleções e convites especiais.</p><form class="newsletter-form" action="{{ route('newsletter') }}" method="post">@csrf<input type="email" required name="email" placeholder="Seu e-mail"><button aria-label="Inscrever">→</button><label class="checkline"><input type="checkbox" name="consent" required> Concordo em receber comunicações.</label></form></div></div><div class="footer-base container"><span>© {{ date('Y') }} {{ $settings['store_name'] ?? 'Aurora Multimarcas' }} · Demonstração local</span><span>{{ config('shop.payment_mode')==='demo'?'Pagamentos e fretes demonstrativos; nenhuma cobrança real.':'Fretes demonstrativos; pagamentos dependem de provedor externo.' }}</span></div></footer>
@if(!file_exists(public_path('build/manifest.json')))<script src="{{ asset('assets/aurora.js') }}" defer></script>@endif
</body></html>
