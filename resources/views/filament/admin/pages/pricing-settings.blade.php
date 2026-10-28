<x-filament-panels::page>
    <form wire:submit="save" class="grid gap-6">
        {{ $this->form }}

        <div class="flex items-center gap-4">
            <x-filament::button type="submit">Save pricing</x-filament::button>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Example: a merchant processing KES 250,000 in a month pays
                <strong>KES {{ number_format(\App\Support\Pricing::feeFor(250000), 2) }}</strong>.
            </span>
        </div>
    </form>
</x-filament-panels::page>
