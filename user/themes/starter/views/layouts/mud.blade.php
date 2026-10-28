@extends('layouts.base')

@section('content')
<link rel="stylesheet" href="{{ $gspotMudCss ?? '/plugin-assets/gspot-mud/css/gspot-mud-fences.css' }}">
<style>
  .site-content { background: hsl(270 22% 7%); color: #e8e0f0; }
  .site-header, .site-footer { background: hsl(270 18% 10%); border-color: hsl(270 15% 20%); }
  .mud-page--gspot { --bg: hsl(270 22% 7%); --text: #e8e0f0; --muted: #a89bb8; --bg-card: hsl(270 18% 12%); --border: hsl(270 15% 22%); --accent: hsl(300 85% 55%); }
</style>
<div class="mud-page mud-page--gspot">
  <article class="mud-content">
    <h1>{{ $page->title() }}</h1>
    {!! $page->content() !!}
  </article>
</div>
@endsection
