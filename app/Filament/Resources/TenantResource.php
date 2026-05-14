<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon  = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Businesses';
    protected static ?string $navigationLabel = 'Businesses';
    protected static ?string $modelLabel      = 'Business';
    protected static ?int    $navigationSort  = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Business Info')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set, $context) =>
                        $context === 'create'
                            ? $set('subdomain', Str::slug($state))
                            : null
                    ),

                Forms\Components\TextInput::make('subdomain')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->helperText('Unique identifier, e.g. my-shop'),

                Forms\Components\Select::make('plan')
                    ->options([
                        'trial'    => 'Trial',
                        'starter'  => 'Starter',
                        'pro'      => 'Pro',
                        'enterprise' => 'Enterprise',
                    ])
                    ->default('trial')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'active'    => 'Active',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('active')
                    ->required(),

                Forms\Components\DateTimePicker::make('trial_ends_at')
                    ->label('Trial Ends At')
                    ->visible(fn (Forms\Get $get) => $get('plan') === 'trial'),
            ])->columns(2),

            Forms\Components\Section::make('Business Settings')->schema([
                Forms\Components\TextInput::make('settings.business_name')
                    ->label('Business Name (Display)')
                    ->maxLength(255),

                Forms\Components\TextInput::make('settings.business_phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(20),

                Forms\Components\TextInput::make('settings.business_address')
                    ->label('Address')
                    ->maxLength(255),

                Forms\Components\TextInput::make('settings.business_city')
                    ->label('City')
                    ->maxLength(100),

                Forms\Components\Select::make('settings.language')
                    ->label('Language')
                    ->options(['en' => 'English', 'ur' => 'Urdu'])
                    ->default('en'),

                Forms\Components\Select::make('settings.invoice_template')
                    ->label('Invoice Template')
                    ->options(['thermal' => 'Thermal', 'a4' => 'A4'])
                    ->default('thermal'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('subdomain')
                    ->searchable()
                    ->copyable()
                    ->color('gray'),

                Tables\Columns\BadgeColumn::make('plan')
                    ->colors([
                        'warning'  => 'trial',
                        'primary'  => 'starter',
                        'success'  => 'pro',
                        'info'     => 'enterprise',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'suspended',
                        'gray'    => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')
                    ->options([
                        'trial'      => 'Trial',
                        'starter'    => 'Starter',
                        'pro'        => 'Pro',
                        'enterprise' => 'Enterprise',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'    => 'Active',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record) => $record->status === 'active')
                    ->action(fn (Tenant $record) => $record->update(['status' => 'suspended'])),

                Tables\Actions\Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tenant $record) => $record->status !== 'active')
                    ->action(fn (Tenant $record) => $record->update(['status' => 'active'])),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            TenantResource\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit'   => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
