{{-- Conversation thread. $ticket, $staffView (bool) --}}
<div class="space-y-4">
    @foreach ($ticket->messages as $m)
        @continue($m->author_type === 'note' && ! $staffView)
        @php
            $isStaff = $m->author_type === 'staff';
            $isNote = $m->author_type === 'note';
            $mine = $staffView ? ($isStaff || $isNote) : ! $isStaff;
        @endphp
        <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[85%] sm:max-w-[75%]">
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500 {{ $mine ? 'justify-end' : '' }}">
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $m->author_name }}</span>
                    @if ($isStaff)<span class="rounded bg-primary-50 px-1.5 py-0.5 font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">CoinBox</span>@endif
                    @if ($isNote)<span class="rounded bg-warning-50 px-1.5 py-0.5 font-semibold text-warning-700 dark:bg-warning-400/10 dark:text-warning-300">Internal note</span>@endif
                    <span title="{{ $m->created_at->setTimezone('Africa/Nairobi')->format('j M Y, H:i') }}">{{ $m->created_at->diffForHumans() }}</span>
                </div>
                <div class="whitespace-pre-line rounded-2xl px-4 py-3 text-sm leading-relaxed
                    {{ $isNote ? 'bg-warning-50 text-warning-950 ring-1 ring-warning-200 dark:bg-warning-400/10 dark:text-warning-100 dark:ring-warning-400/20' : ($isStaff ? 'bg-primary-600 text-white' : 'bg-white text-gray-900 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-100 dark:ring-white/10') }}
                    {{ $mine ? 'rounded-tr-sm' : 'rounded-tl-sm' }}">{{ $m->body }}</div>
                @if ($isStaff && $staffView && ($m->sent_to || $m->cc))
                    <p class="mt-1 text-right text-xs text-gray-500">Emailed to {{ $m->sent_to }}@if ($m->cc) · cc {{ implode(', ', $m->cc) }}@endif</p>
                @endif
            </div>
        </div>
    @endforeach
</div>
