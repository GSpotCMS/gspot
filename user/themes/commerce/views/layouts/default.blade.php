@extends('layouts.base')

@section('content')
@if($page->slug() === 'home')
    @include('partials.store-home')
@elseif($page->slug() === 'shop')
    @include('partials.store-shop-page')
@else
<div class="store-arena store-arena--article">
    <article class="page page--commerce">
        <h1>{{ $page->title() }}</h1>
        <div class="page-content">
            {!! $page->content() !!}
        </div>
    </article>
</div>
@endif
@endsection
