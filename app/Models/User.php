<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Notifications\ResetPasswordNotification;
use App\Support\Cpf;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $cpf
 * @property UserType $type
 * @property string|null $oab_number
 * @property string|null $oab_uf
 * @property UserStatus $status
 * @property \Illuminate\Support\Carbon|null $last_login_at
 */
#[Fillable(['name', 'email', 'password', 'cpf', 'type', 'oab_number', 'oab_uf'])]
#[Hidden(['password'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'type' => UserType::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    /** E-mail is always stored trimmed and lowercase. */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)),
        );
    }

    /** CPF is always stored as digits only. */
    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Cpf::normalize($value),
        );
    }

    /** OAB UF is always stored uppercase. */
    protected function oabUf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : mb_strtoupper(trim($value)),
        );
    }

    /** "123456/BA", or null when the user has no OAB registration. */
    public function oab(): ?string
    {
        return $this->oab_number && $this->oab_uf ? "{$this->oab_number}/{$this->oab_uf}" : null;
    }

    public function isAdmin(): bool
    {
        return $this->type === UserType::Admin;
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Bloqueado;
    }

    // ---- JWTSubject ------------------------------------------------------

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** Extra claims; no personal data (CPF/OAB) ever goes into the token. */
    public function getJWTCustomClaims(): array
    {
        return [
            'type' => $this->type->value,
            'pwv' => $this->passwordVersion(),
        ];
    }

    /**
     * Opaque fingerprint of the current password hash. It changes whenever the
     * password changes, so tokens issued before a reset stop matching (REQ02).
     * HMAC with the app key: reveals nothing about the hash itself.
     */
    public function passwordVersion(): string
    {
        return substr(hash_hmac('sha256', (string) $this->getAuthPassword(), (string) config('app.key')), 0, 16);
    }

    /** Sends the REQ02 e-mail with the front-end link. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
