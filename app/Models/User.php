<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Shared\Authorization\AdminPanelAccessService;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'login_enabled' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->account_status === 'suspended';
    }

    public function canAuthenticate(): bool
    {
        return $this->account_status === 'active'
            && ($this->login_enabled ?? true)
            && ! $this->must_change_password;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return app(AdminPanelAccessService::class)->canAccess($this, $panel);
    }

    public function getFilamentName(): string
    {
        return $this->email ?? 'Administrator';
    }
}
