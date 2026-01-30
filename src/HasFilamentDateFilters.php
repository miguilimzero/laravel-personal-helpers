<?php

namespace Miguilim\Helpers;

use Carbon\Carbon;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;

use Filament\Schemas\Schema;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

use Miguilim\Helpers\Agent;

trait HasFilamentDateFilters
{
    use HasFiltersForm;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->columns([
            'md' => 1,
            'xl' => 1,
            '2xl' => 1,
        ])->components([
            Section::make()
                ->columns(7)
                ->schema([
                    Select::make('preset')
                        ->options([
                            'today' => 'Today',
                            'yesterday' => 'Yesterday',
                            '7d' => '7 Days',
                            '30d' => '30 Days',
                            '90d' => '90 Days',
                            '365d' => '365 Days',
                            'custom' => 'Custom',
                        ])
                        ->reactive()
                        ->selectablePlaceholder(false)
                        ->afterStateUpdated(function (Set $set, string $state) {
                            $this->updateDateFields($set, $state);
                        })
                        ->default(Agent::currentRequest()->isDesktop() ? '30d' : '7d'),

                    Select::make('interval')
                        ->options([
                            'perHour' => 'Per Hour',
                            'perDay' => 'Per Day',
                            'perWeek' => 'Per Week',
                            'perMonth' => 'Per Month',
                        ])
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            $this->checkValidation($set, $get);
                        })
                        ->disableOptionWhen(function ($value, Get $get) {
                            return $value === 'perHour' && $get('startDate') !== $get('endDate');
                        })
                        ->selectablePlaceholder(false)
                        ->default('perDay')
                        ->columnSpan(2),

                    DatePicker::make('startDate')
                        ->label('Start Date')
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            $this->checkValidation($set, $get);
                        })
                        ->native(false)
                        ->default(today()->subDays(Agent::currentRequest()->isDesktop() ? 30 : 7)->startOfDay())
                        ->columnSpan(2),

                    DatePicker::make('endDate')
                        ->label('End Date')
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            $this->checkValidation($set, $get);
                        })
                        ->native(false)
                        ->minDate(fn (Get $get) => $get('startDate'))
                        ->maxDate(today()->endOfDay())
                        ->default(today()->endOfDay())
                        ->columnSpan(2),
                ]),
        ]);
    }

    protected function checkValidation(Set $set, Get $get): void
    {
        $set('preset', 'custom');

        if ($get('interval') === 'perHour' && $get('startDate') !== $get('endDate')) {
            $set('interval', 'perDay');
        }

        if ($get('startDate') && $get('endDate') && Carbon::parse($get('endDate'))->lt(Carbon::parse($get('startDate')))) {
            $set('endDate', $get('startDate'));
        }

        $this->updateDateFields($set, $get('preset'));
    }

    protected function updateDateFields(Set $set, string $preset): void
    {
        $today  = Carbon::today()->startOfDay();
        $format = 'Y-m-d H:i:s';

        if ($preset === 'today') {
            $set('startDate', $today->format($format));
            $set('endDate', $today->copy()->endOfDay()->format($format));
        }
        if ($preset === 'yesterday') {
            $set('startDate', $today->copy()->subDay()->format($format));
            $set('endDate', $today->copy()->endOfDay()->subDay()->format($format));
        }
        if ($preset === '7d') {
            $set('startDate', $today->copy()->subDays(7)->format($format));
            $set('endDate', $today->copy()->endOfDay()->format($format));
        }
        if ($preset === '30d') {
            $set('startDate', $today->copy()->subDays(30)->format($format));
            $set('endDate', $today->copy()->endOfDay()->format($format));
        }
        if ($preset === '90d') {

            $set('startDate', $today->copy()->subDays(90)->format($format));
            $set('endDate', $today->copy()->endOfDay()->format($format));
        }
        if ($preset === '365d') {
            $set('startDate', $today->copy()->subDays(365)->format($format));
            $set('endDate', $today->copy()->endOfDay()->format($format));
        }

        if ($preset !== 'custom') {
            $set('interval', match ($preset) {
                'today'     => 'perHour',
                'yesterday' => 'perHour',
                '90d'       => 'perWeek',
                '365d'      => 'perMonth',
                default     => 'perDay',
            });
        }
    }
}
