@extends('layouts.base')

@section('content')
<div class="forum-thread">
    <h1>{{ $thread['title'] ?? 'Thread' }}</h1>
    <div class="thread-meta">
        <span>by {{ $thread['author'] ?? 'Unknown' }}</span>
        <time>{{ $thread['created'] ?? '' }}</time>
    </div>
    <div class="thread-content">
        {!! $thread['content'] ?? '' !!}
    </div>

    <h2>Replies ({{ count($thread['replies'] ?? []) }})</h2>
    @forelse($thread['replies'] ?? [] as $reply)
        <div class="reply">
            <div class="reply-meta">
                <strong>{{ $reply['author'] }}</strong>
                <time>{{ $reply['created'] }}</time>
            </div>
            <div class="reply-content">{!! $reply['content'] !!}</div>
        </div>
    @empty
        <p>No replies yet. Be the first!</p>
    @endforelse
</div>
@endsection
