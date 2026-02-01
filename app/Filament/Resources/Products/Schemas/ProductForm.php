<?php

namespace Modules\Billing\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Modules\Billing\Enums\BillingScheme;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                // Left Column - Form Fields (8 columns = 2/3 width)
                Grid::make(1)
                    ->schema([
                        Section::make(__('Basic Information'))
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('sku')
                                    ->label(__('SKU'))
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->helperText(__('Leave empty to auto-generate from product name'))
                                    ->maxLength(100),

                                TextInput::make('slug')
                                    ->label(__('Slug'))
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->maxLength(255)
                                    ->helperText(__('Leave empty to auto-generate from product name'))
                                    ->columnSpanFull(),

                                RichEditor::make('description')
                                    ->label(__('Description'))
                                    ->nullable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make(__('Visibility'))
                            ->schema([
                                Toggle::make('is_active')
                                    ->label(__('Active'))
                                    ->helperText(__('When disabled, the product cannot be purchased or used in the system'))
                                    ->onColor('success')
                                    ->default(true),

                                Toggle::make('is_visible')
                                    ->label(__('Visible'))
                                    ->helperText(__('When enabled, the product appears in public listings and pricing pages'))
                                    ->onColor('success')
                                    ->default(true),

                                Toggle::make('is_highlighted')
                                    ->label(__('Highlighted'))
                                    ->helperText(__('When enabled, the product is featured prominently (e.g., "Most Popular" badge)'))
                                    ->onColor('success')
                                    ->default(false),
                            ])
                            ->columns(1),

                        Section::make(__('Marketing feature list'))
                            ->description(__('List the key features or benefits of this product. These will be displayed as bullet points.'))
                            ->schema([
                                Repeater::make('features')
                                    ->label(__('Features'))
                                    ->simple(
                                        TextInput::make('feature')
                                            ->label(__('Feature'))
                                            ->required()
                                            ->maxLength(255)
                                    )
                                    ->addActionLabel(__('Add Feature'))
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                            ]),

                        Section::make(__('Pricing'))
                            ->description(__('Configure pricing options for this product.'))
                            ->schema([
                                Repeater::make('prices')
                                    ->relationship()
                                    ->label(__('Prices'))
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('amount')
                                                ->label(__('Amount (cents)'))
                                                ->numeric()
                                                ->required()
                                                ->minValue(0)
                                                ->helperText(__('Enter price in cents (e.g., 900 = $9.00)')),

                                            Select::make('currency')
                                                ->label(__('Currency'))
                                                ->options([
                                                    'usd' => 'USD',
                                                    'eur' => 'EUR',
                                                    'gbp' => 'GBP',
                                                ])
                                                ->default('usd')
                                                ->required(),

                                            Select::make('billing_scheme')
                                                ->label(__('Billing Scheme'))
                                                ->options(BillingScheme::class)
                                                ->default(BillingScheme::FlatAmount)
                                                ->required(),
                                        ]),

                                        Grid::make(3)->schema([
                                            Select::make('interval')
                                                ->label(__('Billing Interval'))
                                                ->options([
                                                    'day' => __('Daily'),
                                                    'week' => __('Weekly'),
                                                    'month' => __('Monthly'),
                                                    'year' => __('Yearly'),
                                                ])
                                                ->placeholder(__('One-time (no interval)')),

                                            TextInput::make('interval_count')
                                                ->label(__('Interval Count'))
                                                ->numeric()
                                                ->minValue(1)
                                                ->default(1)
                                                ->helperText(__('e.g., 3 for quarterly')),

                                            Toggle::make('is_active')
                                                ->label(__('Active'))
                                                ->default(true)
                                                ->onColor('success'),
                                        ]),

                                        TextInput::make('provider_price_id')
                                            ->label(__('Provider Price ID'))
                                            ->helperText(__('Stripe price ID (e.g., price_xxx)'))
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel(__('Add Price'))
                                    ->defaultItems(0)
                                    ->columnSpanFull()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => isset($state['amount'], $state['currency'])
                                            ? '$'.number_format($state['amount'] / 100, 2).' '.strtoupper($state['currency']).($state['interval'] ? '/'.$state['interval'] : ' one-time')
                                            : null
                                    ),
                            ]),

                        Section::make(__('Metadata'))
                            ->description(__('Add custom key-value pairs for any additional information (e.g., trial_days: 14, max_users: 100).'))
                            ->schema([
                                KeyValue::make('metadata')
                                    ->label(__('Metadata'))
                                    ->keyLabel(__('Property name'))
                                    ->valueLabel(__('Property value'))
                                    ->addActionLabel(__('Add Metadata'))
                                    ->columnSpanFull(),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(8),

                // Right Column - Guidance Panel (4 columns = 1/3 width)
                Grid::make(1)
                    ->schema([
                        Section::make(__('Product Creation Guide'))
                            ->schema([
                                Text::make(__('Basic Information'))
                                    ->content(__('Start by entering the product name, SKU (unique identifier), and a URL-friendly slug. The display order determines how products appear in lists (lower numbers first).')),

                                Text::make(__('Features'))
                                    ->content(__('List the key features or benefits of this product. These will be displayed as bullet points to help customers understand what they get (e.g., "Unlimited storage", "24/7 support", "Advanced analytics").')),

                                Text::make(__('Metadata'))
                                    ->content(__('Add custom key-value pairs for any additional information (e.g., trial_days: 14, max_users: 100). This data can be used for custom integrations.')),
                            ])
                            ->icon('heroicon-o-information-circle')
                            ->iconColor('primary'),
                    ])
                    ->columnSpan(4),
            ]);
    }
}
