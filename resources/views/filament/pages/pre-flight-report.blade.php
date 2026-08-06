<div class="space-y-6">
    <x-filament-panels::page>
        @if ($this->report['valid'])
            <x-filament::section color="success">
                <div class="flex items-center gap-2 text-success-600">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6" />
                    <div>
                        <p class="text-sm font-semibold">Batch is exportable</p>
                        <p class="text-xs">No blocking errors were found. The XSD gate will still run at export time.</p>
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section color="danger">
                <div class="flex items-center gap-2 text-danger-600">
                    <x-filament::icon icon="heroicon-o-x-circle" class="h-6 w-6" />
                    <div>
                        <p class="text-sm font-semibold">{{ count($this->report['errors']) }} blocking error(s)</p>
                        <p class="text-xs">Resolve the errors below before exporting, or use the "Export XML" action on the batch view to force.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="Blocking errors">
            @forelse ($this->report['errors'] as $error)
                <div class="mb-3 flex items-start gap-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-danger-700 dark:border-danger-900 dark:bg-danger-950">
                    <span class="mt-0.5 rounded bg-danger-600 px-1.5 py-0.5 text-xs font-semibold text-white">{{ $error['code'] }}</span>
                    <div>
                        <p class="text-sm">{{ $error['message'] }}</p>
                        @if ($error['claim_number'])
                            <p class="text-xs opacity-80">Claim: {{ $error['claim_number'] }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No blocking errors.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Warnings">
            @forelse ($this->report['warnings'] as $warning)
                <div class="mb-2 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-700 dark:border-amber-900 dark:bg-amber-950">
                    <span class="mt-0.5 rounded bg-amber-600 px-1.5 py-0.5 text-xs font-semibold text-white">{{ $warning['code'] }}</span>
                    <div>
                        <p class="text-sm">{{ $warning['message'] }}</p>
                        @if ($warning['claim_number'])
                            <p class="text-xs opacity-80">Claim: {{ $warning['claim_number'] }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No warnings.</p>
            @endforelse
        </x-filament::section>
    </x-filament-panels::page>
</div>
