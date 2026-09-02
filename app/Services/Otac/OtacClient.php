<?php

namespace Modules\Insurance\Services\Otac;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Insurance\Settings\InsuranceSettings;

/**
 * HTTP client for the NHIA OTAC portal (otac.nhia.gov.gh).
 *
 * Handles login, token caching, and attendance generation. Access tokens are
 * valid for ~2 hours; the cached copy expires 5 minutes early and an hourly
 * scheduled re-login keeps it warm (see insurance:otac-refresh-token).
 */
class OtacClient
{
    private const TOKEN_CACHE_KEY = 'insurance_otac_access_token';

    public function __construct(private InsuranceSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->otac_enabled
            && filled($this->settings->otac_username)
            && filled($this->settings->otac_password);
    }

    public function login(bool $force = false): ?string
    {
        if (! $force) {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->post("{$this->baseUrl()}/api/login", [
                'username' => (string) $this->settings->otac_username,
                'password' => (string) $this->settings->otac_password,
            ]);

        if (! $response->successful()) {
            Log::warning('nhis.otac.login_failed', ['status' => $response->status()]);

            return null;
        }

        $token = $response->json('accessToken.token');
        $expiresIn = (int) ($response->json('accessToken.expiresIn') ?? 7200);

        if (! is_string($token) || $token === '') {
            Log::warning('nhis.otac.login_failed', ['status' => $response->status(), 'reason' => 'missing token']);

            return null;
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds(max(60, $expiresIn - 300)));

        return $token;
    }

    public function accessToken(): ?string
    {
        return $this->login();
    }

    /**
     * @return array{ok: bool, status: int, body: mixed}
     */
    public function hpInfo(): array
    {
        $response = $this->requestWithAuth(fn (string $token): Response => Http::timeout($this->timeout())
            ->withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl()}/api/attendance/hpinfo"));

        return [
            'ok' => $response !== null && $response->successful(),
            'status' => $response?->status() ?? 0,
            'body' => $response?->json(),
        ];
    }

    /**
     * Generate an NHIS attendance (and claim check code) for a card holder.
     *
     * WARNING: a successful call creates a real attendance log at NHIA — only
     * call when an encounter is genuinely being created.
     *
     * @return array{statusCode: int, statusMessage: string, attendanceData?: array<string, mixed>|null}
     */
    public function generateAttendance(string $cardType, string $cardNo): array
    {
        $response = $this->requestWithAuth(fn (string $token): Response => Http::timeout($this->timeout())
            ->withToken($token)
            ->acceptJson()
            ->post("{$this->baseUrl()}/api/attendance/generate", [
                'otac' => null,
                'bioMatchResult' => null,
                'bmasTransactionID' => null,
                'cardType' => $cardType,
                'cardNo' => $cardNo,
            ]));

        if ($response === null || ! $response->successful()) {
            return [
                'statusCode' => -1,
                'statusMessage' => 'OTAC request failed (HTTP '.($response?->status() ?? 'no response').').',
                'attendanceData' => null,
            ];
        }

        $body = $response->json();

        return is_array($body) ? $body : [
            'statusCode' => -1,
            'statusMessage' => 'OTAC returned an unreadable response.',
            'attendanceData' => null,
        ];
    }

    public function forgetToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * Run an authenticated request, re-logging in once on HTTP 401.
     *
     * @param  callable(string): Response  $request
     */
    private function requestWithAuth(callable $request): ?Response
    {
        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        $response = $request($token);

        if ($response->status() === 401) {
            $this->forgetToken();
            $token = $this->login();

            if ($token === null) {
                return $response;
            }

            $response = $request($token);
        }

        return $response;
    }

    private function timeout(): int
    {
        return (int) config('insurance.otac.timeout', 15);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('insurance.otac.base_url', 'https://otac.nhia.gov.gh'), '/');
    }
}
