@extends('layouts.base')

@section('content')
@if($page->slug() === 'home')
    @include('partials.magazine-home')
@else
<div class="magazine-shell magazine-shell--article">
    <article class="page page--editorial magazine-article">
        <div class="magazine-article-main">
            <header class="page-masthead">
                <p class="page-kicker">Section</p>
                <h1>{{ $page->title() }}</h1>
            </header>
            <div class="page-content">
                {!! $page->content() !!}
            </div>
        </div>
        <aside class="magazine-rail magazine-rail--article" aria-label="Related">
            <h2 class="magazine-rail-title">Elsewhere</h2>
            <ul class="magazine-rail-list">
                <li><a href="/">Front page</a></li>
                <li><a href="/docs">Documentation</a></li>
                <li><a href="/pattern-demo">Pattern demo</a></li>
                <li><a href="/shop">Shop</a></li>
            </ul>
        </aside>
    </article>
</div>
@endif
@endsection
