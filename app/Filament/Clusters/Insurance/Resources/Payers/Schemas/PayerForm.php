<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Services\Connectors\PrivateInsurer\HttpPrivateInsurerConnector;
use Modules\Insurance\Services\PayerConnectorRegistry;

class PayerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->required(),
                TextInput::make('name')->required(),
                // A second NHIS payer cannot be created; the seeded NHIS record
                // keeps its type listed (and locked) so it can still be edited.
                Select::make('type')
                    ->options(fn (?Payer $record): array => collect(PayerType::cases())
                        ->reject(fn (PayerType $type) => $type === PayerType::NHIS && ! $record?->isSystem())
                        ->mapWithKeys(fn (PayerType $type) => [$type->value => $type->getLabel()])
                        ->all())
                    ->disabled(fn (?Payer $record): bool => (bool) $record?->isSystem())
                    ->dehydrated(fn (?Payer $record): bool => ! $record?->isSystem())
                    ->required(),
                KeyValue::make('config'),
                Section::make(__('Claims connector'))
                    ->description(__('How claims, tariff catalogs and claim decisions are exchanged with this payer. NHIS always uses batch XML export.'))
                    ->hidden(fn (?Payer $record, Get $get): bool => (bool) $record?->isSystem() || $get('type') === PayerType::NHIS->value || $get('type') === PayerType::NHIS)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('connector_code')
                            ->label(__('Connector'))
                            ->options(PayerConnectorRegistry::options())
                            ->default('private-generic')
                            ->live()
                            ->columnSpanFull(),
                        Group::make()
                            ->statePath('connector_config')
                            ->visible(fn (Get $get): bool => $get('connector_code') === HttpPrivateInsurerConnector::CODE)
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                TextInput::make('base_url')
                                    ->label(__('Base URL'))
                                    ->url()
                                    ->required()
                                    ->columnSpanFull(),
                                Select::make('auth_type')
                                    ->label(__('Authentication'))
                                    ->options([
                                        'none' => __('None'),
                                        'bearer' => __('Bearer token'),
                                        'basic' => __('Username and password'),
                                        'api_key' => __('API key header'),
                                    ])
                                    ->default('bearer')
                                    ->live(),
                                TextInput::make('timeout')
                                    ->label(__('Timeout (seconds)'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(120)
                                    ->default(20),
                                TextInput::make('token')
                                    ->label(__('Token'))
                                    ->password()
                                    ->revealable()
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'bearer'),
                                TextInput::make('username')
                                    ->label(__('Username'))
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'basic'),
                                TextInput::make('password')
                                    ->label(__('Password'))
                                    ->password()
                                    ->revealable()
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'basic'),
                                TextInput::make('api_key_header')
                                    ->label(__('API key header'))
                                    ->default('X-API-Key')
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'api_key'),
                                TextInput::make('api_key')
                                    ->label(__('API key'))
                                    ->password()
                                    ->revealable()
                                    ->visible(fn (Get $get): bool => $get('auth_type') === 'api_key'),
                                TextInput::make('catalog_path')
                                    ->label(__('Tariff catalog path'))
                                    ->placeholder('/tariffs'),
                                TextInput::make('claim_path')
                                    ->label(__('Claim submission path'))
                                    ->placeholder('/claims'),
                                TextInput::make('feedback_path')
                                    ->label(__('Claim status path'))
                                    ->placeholder('/claims/{reference}')
                                    ->helperText(__('{reference} is replaced with the payer\'s claim reference.')),
                                TextInput::make('health_path')
                                    ->label(__('Health-check path'))
                                    ->placeholder('/'),
                                KeyValue::make('feedback_status_map')
                                    ->label(__('Claim status mapping'))
                                    ->keyLabel(__('Payer status'))
                                    ->valueLabel(__('approved / partial / rejected / pending'))
                                    ->columnSpanFull(),
                                KeyValue::make('field_map')
                                    ->label(__('Response field paths (advanced)'))
                                    ->helperText(__('Override response keys, e.g. catalog_items_key = data.items, claim_reference_key = claimId.'))
                                    ->keyLabel(__('Setting'))
                                    ->valueLabel(__('Dot path'))
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
