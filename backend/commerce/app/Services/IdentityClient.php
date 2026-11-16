<?php

namespace App\Services;

use App\Auth\IdentityUser;
use App\Exceptions\IdentityUnavailableException;
use Illuminate\Http\Client\Factory as HttpClient;
use Throwable;

/**
 * REST call to Identity (ADR-011: interactive operations use REST). Commerce
 * never reads Identity's database (ADR-012).
 */
class IdentityClient
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Billing data for T05: only what the user already registered.
     * The CPF arrives (and is stored) already masked.
     *
     * @return array{name: string, email: string, cpf_masked: ?string}
     */
    public function billingDataFor(IdentityUser $user): array
    {
        try {
            $response = $this->http
                ->baseUrl(config('commerce.identity.url'))
                ->timeout(config('commerce.identity.timeout'))
                ->acceptJson()
                ->withToken($user->token())
                ->get('/api/v1/me');
        } catch (Throwable $e) {
            throw new IdentityUnavailableException($e::class);
        }

        if (! $response->successful()) {
            throw new IdentityUnavailableException('HTTP '.$response->status());
        }

        $data = $response->json('data');

        if (! is_array($data) || ($data['id'] ?? null) !== $user->id || empty($data['name']) || empty($data['email'])) {
            throw new IdentityUnavailableException('resposta inesperada');
        }

        return [
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'cpf_masked' => isset($data['cpf']) ? (string) $data['cpf'] : null,
        ];
    }
}
