<?php

namespace Modules\Billing\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Basic Information'))
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name'))
                            ->columnSpanFull(),

                        TextEntry::make('slug')
                            ->label(__('Slug'))
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('sku')
                            ->label(__('SKU'))
                            ->copyable(),

                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->html()
                            ->columnSpanFull(),

                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(),

                        TextEntry::make('deleted_at')
                            ->label(__('Deleted At'))
                            ->dateTime()
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->hidden(fn ($record) => $record->deleted_at === null),
                    ])
                    ->columns(2)
                    ->columnSpan(1),

                Grid::make(1)
                    ->schema([
                        Section::make(__('Visibility'))
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label(__('Active'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                IconEntry::make('is_visible')
                                    ->label(__('Visible'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-eye')
                                    ->falseIcon('heroicon-o-eye-slash')
                                    ->trueColor('success')
                                    ->falseColor('gray'),

                                IconEntry::make('is_highlighted')
                                    ->label(__('Highlighted'))
                                    ->boolean()
                                    ->trueIcon('heroicon-s-star')
                                    ->falseIcon('heroicon-o-star')
                                    ->trueColor('warning')
                                    ->falseColor('gray'),
                            ])
                            ->columns(3),

                        Section::make(__('Features'))
                            ->schema([
                                TextEntry::make('features')
                                    ->hiddenLabel()
                                    ->listWithLineBreaks()
                                    ->icon('heroicon-o-check-circle')
                                    ->iconColor('success')
                                    ->hidden(fn ($record) => empty($record->features)),

                                TextEntry::make('no_features')
                                    ->hiddenLabel()
                                    ->state(__('This product has no features...'))
                                    ->icon('heroicon-o-information-circle')
                                    ->color('gray')
                                    ->hidden(fn ($record) => ! empty($record->features)),
                            ])->collapsible(),

                        Section::make(__('Metadata'))
                            ->schema([
                                KeyValueEntry::make('metadata')
                                    ->hiddenLabel()
                                    ->keyLabel(__('Key'))
                                    ->valueLabel(__('Value'))
                                    ->hidden(fn ($record) => empty($record->metadata)),

                                TextEntry::make('no_metadata')
                                    ->hiddenLabel()
                                    ->state(__('This product has no metadata...'))
                                    ->icon('heroicon-o-information-circle')
                                    ->color('gray')
                                    ->hidden(fn ($record) => ! empty($record->metadata)),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
