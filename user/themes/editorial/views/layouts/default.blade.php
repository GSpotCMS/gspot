@extends('layouts.base')

@section('content')
<article class="page page--editorial">
    <header class="page-masthead">
        <p class="page-kicker">Feature</p>
        <h1>{{ $page->title() }}</h1>
    </header>
    <div class="page-content">
        {!! $page->content() !!}
    </div>
</article>
@endsection
