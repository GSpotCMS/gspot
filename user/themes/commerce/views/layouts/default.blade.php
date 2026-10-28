@extends('layouts.base')

@section('content')
<article class="page page--commerce">
    <h1>{{ $page->title() }}</h1>
    <div class="page-content">
        {!! $page->content() !!}
    </div>
</article>
@endsection
