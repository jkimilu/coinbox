@php
    $user = filament()->auth()->user();
    $profileUrl = filament()->getProfileUrl();
    $active = $profileUrl && url()->current() === $profileUrl;
@endphp
@if ($user && $profileUrl)
    <div style="padding:.75rem;border-top:1px solid var(--gray-200)" class="dark:!border-white/10">
        <a href="{{ $profileUrl }}" wire:navigate
           title="Your profile and sign-in security"
           style="display:flex;align-items:center;gap:.75rem;padding:.55rem .6rem;border-radius:.65rem;text-decoration:none;{{ $active ? 'background:var(--primary-50);' : '' }}"
           class="hover:!bg-gray-100 dark:hover:!bg-white/5 {{ $active ? 'dark:!bg-white/10' : '' }}">
            <img src="{{ filament()->getUserAvatarUrl($user) }}" alt="" style="width:2.25rem;height:2.25rem;border-radius:999px;flex-shrink:0">
            <span style="min-width:0;flex:1">
                <span style="display:block;font-size:.875rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--gray-950)" class="dark:!text-white">{{ $user->name }}</span>
                <span style="display:block;font-size:.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--gray-500)">{{ $user->email }}</span>
            </span>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="color:var(--gray-400);flex-shrink:0"><path d="M6 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </div>
@endif
