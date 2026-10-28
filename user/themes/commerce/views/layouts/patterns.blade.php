@extends('layouts.base')

@section('content')
<link rel="stylesheet" href="{{ $gspotPatternsCss ?? '/plugin-assets/gspot-patterns/css/gspot-patterns-fences.css' }}">
<div class="pattern-page pattern-page--gspot">
  <article class="pattern-content">
    <h1>{{ $page->title() }}</h1>
    {!! $page->content() !!}
  </article>
</div>
@endsection
