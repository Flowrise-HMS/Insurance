<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Services\MemberVerificationService;

class VerifyMemberAction
{
    public static function make(): Action
    {
        return Action::make('verifyMember')
            ->label(__('Verify member'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn (PatientPolicy $record): bool => Gate::allows('update', $record))
            ->action(function (PatientPolicy $record): void {
                $service = app(MemberVerificationService::class);
                $service->verify($record);
                $badge = $service->badge($record->refresh());

                Notification::make()
                    ->title($badge['label'])
                    ->color($badge['color'])
                    ->send();
            });
    }
}
