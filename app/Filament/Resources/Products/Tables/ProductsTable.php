<?php

namespace Modules\Billing\Filament\Resources\Products\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\Billing\Enums\ProductType;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('sku')
                    ->label(__('SKU'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state) => $state->label())
                    ->color(fn (ProductType $state): string => match ($state) {
                        ProductType::FREEMIUM => 'success',
                        ProductType::SUBSCRIPTION => 'primary',
                        ProductType::ONE_TIME => 'warning',
                    }),

                ToggleColumn::make('is_active')
                    ->onColor('success'),

                ToggleColumn::make('is_visible')
                    ->onColor('success'),

                ToggleColumn::make('is_highlighted')
                    ->onColor('success'),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label(__('Deleted At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(fn () => collect(ProductType::cases())->mapWithKeys(
                        fn (ProductType $type) => [$type->value => $type->label()]
                    ))
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label(__('Active Only')),

                TernaryFilter::make('is_visible')
                    ->label(__('Visible Only')),

                TernaryFilter::make('is_highlighted')
                    ->label(__('Highlighted Only')),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_order', 'asc')
            ->reorderable('display_order');
    }
}
