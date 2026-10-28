@extends('layouts.base')

@section('content')
<div class="member-profile">
    <div class="profile-header">
        @if($profile['avatar'] ?? false)
            <img src="{{ $profile['avatar'] }}" alt="{{ $profile['display_name'] }}" class="avatar">
        @endif
        <h1>{{ $profile['display_name'] }}</h1>
        <p class="bio">{{ $profile['bio'] }}</p>
        <div class="social-stats">
            <span>{{ count($followers ?? []) }} followers</span>
            <span>{{ count($following ?? []) }} following</span>
        </div>
    </div>

    <h2>Activity Feed</h2>
    <div class="activity-feed">
        @forelse($feed ?? [] as $item)
            <div class="feed-item">
                <strong>{{ $item['actor'] ?? '' }}</strong>
                <span>{{ $item['message'] ?? '' }}</span>
                <time>{{ $item['timestamp'] ?? '' }}</time>
            </div>
        @empty
            <p>No activity yet.</p>
        @endforelse
    </div>
</div>
@endsection
