<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The CRM's own API (config/services.php "crm"): POST /v1/login with the
 * email and password from .env, then bearer requests.
 *
 * The token is cached for a month. A CRM that drops it earlier answers
 * 401: the cached token is forgotten, a new one is logged in once and the
 * request is repeated.
 */
final class CrmApiClient
{
    private const TOKEN_KEY = 'crm_api_token';

    private const TOKEN_TTL_DAYS = 30;

    /*
     * Asked from inside the listener: a slow CRM must not hold it up.
     */
    private const TIMEOUT_SECONDS = 5;

    /**
     * GET /v1/queries?include=operations,sales,payments.currency&search=…
     *
     * @return array<string, mixed> the decoded body
     */
    public function searchQueries(string $search): array
    {
        $response = $this->get('v1/queries', [
            'include' => 'operations,sales,payments.currency',
            'search' => $search,
        ]);

        return (array) $response->json();
    }

    public function configured(): bool
    {
        return $this->baseUrl() !== ''
            && (string) config('services.crm.email') !== ''
            && (string) config('services.crm.password') !== '';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function get(string $path, array $query): Response
    {
        Log::info('Sales turn: CRM request', [
            'url' => $this->baseUrl() . '/' . $path,
            'query' => $query,
            'token_cached' => Cache::has(self::TOKEN_KEY),
        ]);

        $response = $this->request()->get($path, $query);

        if ($response->status() === 401) {
            Log::info('Sales turn: CRM answered 401, logging in again');

            Cache::forget(self::TOKEN_KEY);

            $response = $this->request()->get($path, $query);
        }

        Log::info('Sales turn: CRM response', [
            'url' => (string) $response->effectiveUri(),
            'status' => $response->status(),
            'body_start' => mb_substr($response->body(), 0, 500),
        ]);

        if ($response->failed()) {
            throw new RuntimeException("CRM API {$path} failed with status {$response->status()}");
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->withToken($this->token());
    }

    private function token(): string
    {
        return Cache::remember(
            self::TOKEN_KEY,
            now()->addDays(self::TOKEN_TTL_DAYS),
            fn (): string => $this->login(),
        );
    }

    private function login(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('CRM API is not configured: CRM_API_URL, CRM_API_EMAIL, CRM_API_PASSWORD');
        }

        $response = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post('v1/login', [
                'email' => (string) config('services.crm.email'),
                'password' => (string) config('services.crm.password'),
            ]);

        Log::info('Sales turn: CRM login', ['status' => $response->status()]);

        if ($response->failed()) {
            throw new RuntimeException("CRM API login failed with status {$response->status()}");
        }

        /*
         * Wherever the CRM puts it: {"token"}, {"access_token"} or the
         * same under "data".
         */
        foreach (['token', 'access_token', 'data.token', 'data.access_token'] as $key) {
            $token = $response->json($key);

            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        throw new RuntimeException('CRM API login answered without a token');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.crm.api_url'), '/');
    }
}
