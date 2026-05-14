<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationLabel = 'All Users';
    protected static ?int    $navigationSort  = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context) => $context === 'create')
                    ->label('Password'),

                Forms\Components\Select::make('tenant_id')
                    ->label('Business (Tenant)')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Leave empty for super admin accounts'),

                Forms\Components\Select::make('role')
                    ->options([
                        'owner'   => 'Owner',
                        'manager' => 'Manager',
                        'cashier' => 'Cashier',
                    ])
                    ->default('cashier')
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Forms\Components\Toggle::make('is_super_admin')
                    ->label('Super Admin')
                    ->helperText('Super admins can access this admin panel')
                    ->default(false),
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

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'warning' => 'cashier',
                        'primary' => 'manager',
                        'success' => 'owner',
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\IconColumn::make('is_super_admin')
                    ->boolean()
                    ->label('Super Admin'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'owner'   => 'Owner',
                        'manager' => 'Manager',
                        'cashier' => 'Cashier',
                    ]),

                Tables\Filters\SelectFilter::make('tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Business'),

                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\TernaryFilter::make('is_super_admin')->label('Super Admin'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('deactivate')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->is_active && ! $record->is_super_admin)
                    ->action(fn (User $record) => $record->update(['is_active' => false])),

                Tables\Actions\Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record) => ! $record->is_active)
                    ->action(fn (User $record) => $record->update(['is_active' => true])),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => ! $record->is_super_admin),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
