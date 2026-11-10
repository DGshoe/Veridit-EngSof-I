<?php

namespace App\Services;

use App\Enums\UserType;
use App\Exceptions\Auth\AccountBlockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidTokenException;
use App\Models\User;
use App\Support\IssuedToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * Registration and JWT session lifecycle (REQ01, REQ03, REQ04).
 */
class AuthService
{
    /** Used to spend the same hashing time when the e-mail does not exist. */
    private static ?string $dummyHash = null;

    /**
     * REQ01 — creates an "advogado" or "comum" user. Does NOT log in.
     *
     * @param  array{name: string, email: string, password: string, cpf: string, type: string, oab_number?: ?string, oab_uf?: ?string}  $data
     */
    public function register(array $data): User
    {
        $type = UserType::from($data['type']);

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'cpf' => $data['cpf'],
            'type' => $type,
            'oab_number' => $type === UserType::Advogado ? $data['oab_number'] : null,
            'oab_uf' => $type === UserType::Advogado ? $data['oab_uf'] : null,
        ]);
    }

    /**
     * REQ03 — validates credentials and issues an access token.
     *
     * @throws InvalidCredentialsException|AccountBlockedException
     */
    public function login(string $email, string $password): IssuedToken
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        // Always run a hash check, so response time does not reveal registered e-mails.
        $passwordOk = Hash::check($password, $user?->password ?? self::dummyHash());

        if (! $user || ! $passwordOk) {
            throw new InvalidCredentialsException;
        }

        // Only reveal "blocked" to whoever proved to know the password.
        if ($user->isBlocked()) {
            throw new AccountBlockedException;
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return new IssuedToken($this->guard()->login($user), $user, $this->ttlSeconds());
    }

    /** REQ04 — blacklists the current token. */
    public function logout(): void
    {
        $this->guard()->logout();
    }

    /**
     * Exchanges the current token (even if expired, while inside the refresh
     * window) for a new one. The old token is blacklisted.
     *
     * @throws InvalidTokenException|AccountBlockedException
     */
    public function refresh(): IssuedToken
    {
        $guard = $this->guard();

        try {
            // Validates signature, blacklist and the refresh window (not "exp"),
            // then blacklists the old token.
            $token = $guard->refresh();
            $user = User::query()->find($guard->setToken($token)->getPayload()->get('sub'));
        } catch (JWTException) {
            throw new InvalidTokenException;
        }

        if (! $user || $user->isBlocked()) {
            $guard->invalidate(); // do not leave the freshly issued token usable

            throw $user ? new AccountBlockedException : new InvalidTokenException;
        }

        return new IssuedToken($token, $user, $this->ttlSeconds());
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard */
        return auth('api');
    }

    private function ttlSeconds(): int
    {
        return $this->guard()->factory()->getTTL() * 60;
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(32));
    }
}
