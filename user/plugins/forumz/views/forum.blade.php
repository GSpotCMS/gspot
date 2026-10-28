@extends('layouts.base')

@section('content')
<div class="forum">
    <h1>Forums</h1>

    @forelse($categories ?? [] as $category)
        <div class="forum-category">
            <h2><a href="/forum/{{ $category['slug'] }}">{{ $category['title'] }}</a></h2>
            <p>{{ $category['description'] }}</p>
        </div>
    @empty
        <p>No forum categories yet.</p>
    @endforelse
</div>
@endsection
