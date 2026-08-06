<div>
    <x-filament-panels::page>
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button wire:click="import">
                Import Feedback
            </x-filament::button>
        </div>

        @if ($result)
            <div class="mt-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-300 p-4 dark:border-white/10">
                        <p class="text-sm font-medium text-gray-500">Created</p>
                        <p class="mt-1 text-2xl font-bold">{{ $result['created'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-300 p-4 dark:border-white/10">
                        <p class="text-sm font-medium text-gray-500">Updated</p>
                        <p class="mt-1 text-2xl font-bold">{{ $result['updated'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-300 p-4 dark:border-white/10">
                        <p class="text-sm font-medium text-gray-500">Skipped</p>
                        <p class="mt-1 text-2xl font-bold">{{ $result['skipped'] }}</p>
                    </div>
                </div>

                @if (count($result['errors']) > 0)
                    <div class="mt-6 rounded-xl border border-danger-500 p-4">
                        <p class="text-sm font-semibold text-danger-700">Skipped / unmatched entries</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($result['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </x-filament-panels::page>
</div>
