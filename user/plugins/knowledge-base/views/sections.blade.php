@extends('layouts.base')

@section('content')
<div class="knowledge-base">
    <h1>Knowledge Base</h1>

    <div class="kb-sections">
        @forelse($sections ?? [] as $section)
            <div class="kb-section">
                @if($section['icon'] ?? false)
                    <span class="kb-icon">{{ $section['icon'] }}</span>
                @endif
                <h2><a href="/kb/{{ $section['slug'] }}">{{ $section['title'] }}</a></h2>
                <p>{{ $section['description'] }}</p>
                <small>{{ $section['article_count'] ?? 0 }} articles</small>
            </div>
        @empty
            <p>No knowledge base sections yet.</p>
        @endforelse
    </div>
</div>
@endsection
