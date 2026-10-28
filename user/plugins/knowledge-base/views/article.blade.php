@extends('layouts.base')

@section('content')
<div class="kb-article">
    <div class="article-header">
        <nav class="breadcrumb">
            <a href="/kb">Knowledge Base</a> &raquo;
            <a href="/kb/{{ $section['slug'] ?? '' }}">{{ $section['title'] ?? '' }}</a> &raquo;
            <span>{{ $article['title'] ?? '' }}</span>
        </nav>
        <h1>{{ $article['title'] ?? '' }}</h1>
        <div class="article-meta">
            @if($article['author'] ?? false)
                <span>by {{ $article['author'] }}</span>
            @endif
            @if($article['updated'] ?? false)
                <time>Updated {{ $article['updated'] }}</time>
            @endif
        </div>
    </div>

    <div class="article-layout">
        @if(!empty($article['toc'] ?? []))
            <aside class="floating-toc">
                <h3>Contents</h3>
                <ul>
                    @foreach($article['toc'] as $item)
                        <li class="toc-level-{{ $item['level'] }}">
                            <a href="#{{ $item['id'] }}">{{ $item['text'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </aside>
        @endif

        <div class="article-content">
            {!! $article['raw_content'] ?? '' !!}
        </div>
    </div>

    @if(isset($rating))
        <div class="article-rating">
            <span>Rating: {{ $rating['average'] }}/5 ({{ $rating['count'] }} votes)</span>
        </div>
    @endif
</div>
@endsection
