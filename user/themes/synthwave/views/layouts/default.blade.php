@extends('layouts.base')

@section('content')
@if($page->slug() === 'home')
    @include('partials.synth-home')
@else
<article class="page page--inner synth-inner">
    <h1>{{ $page->title() }}</h1>
    <div class="page-content">
        {!! $page->content() !!}
    </div>
</article>
@endif
@endsection
