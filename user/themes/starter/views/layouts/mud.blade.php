@extends('layouts.base')

@section('content')
<link rel="stylesheet" href="{{ $gspotMudCss ?? '/plugin-assets/gspot-mud/css/gspot-mud-fences.css' }}">
<div class="mud-page mud-page--gspot">
  <article class="mud-content">
    <h1>{{ $page->title() }}</h1>
    {!! $page->content() !!}
  </article>
</div>
@endsection
