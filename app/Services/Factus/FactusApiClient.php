<?php

namespace App\Services\Factus;

use App\Models\ElectronicInvoiceSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FactusApiClient
{
    public function authenticate(ElectronicInvoiceSetting $settings): array
    {
        if (!$settings->client_id || !$settings->client_secret || !$settings->username || !$settings->password) {
            throw new FactusApiException('La configuración de Factus está incompleta.');
        }

        $response = Http::acceptJson()
            ->asForm()
            ->timeout(config('factus.timeout'))
            ->connectTimeout(config('factus.connect_timeout'))
            ->post(rtrim($settings->baseUrl(), '/') . '/oauth/token', [
                'grant_type' => 'password',
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'username' => $settings->username,
                'password' => $settings->password,
            ]);

        if ($response->failed()) {
            throw new FactusApiException(
                'No se pudo autenticar con Factus.',
                ['response' => $response->json() ?: $response->body()],
                $response->status()
            );
        }

        return $response->json();
    }

    public function createBill(ElectronicInvoiceSetting $settings, array $payload): array
    {
        return $this->authorizedRequest($settings)
            ->post('/v2/bills/validate', $payload)
            ->throw(fn ($response) => new FactusApiException(
                'Factus rechazó la factura electrónica.',
                ['response' => $response->json() ?: $response->body()],
                $response->status()
            ))
            ->json();
    }

    public function getBill(ElectronicInvoiceSetting $settings, string $number): array
    {
        return $this->authorizedRequest($settings)
            ->get('/v2/bills/' . $number)
            ->throw(fn ($response) => new FactusApiException(
                'No se pudo consultar la factura en Factus.',
                ['response' => $response->json() ?: $response->body()],
                $response->status()
            ))
            ->json();
    }

    public function downloadPdf(ElectronicInvoiceSetting $settings, string $number): array
    {
        return $this->authorizedRequest($settings)
            ->get('/v2/bills/' . $number . '/download-pdf')
            ->throw(fn ($response) => new FactusApiException(
                'No se pudo descargar el PDF de la factura electrónica.',
                ['response' => $response->json() ?: $response->body()],
                $response->status()
            ))
            ->json();
    }

    public function downloadXml(ElectronicInvoiceSetting $settings, string $number): array
    {
        return $this->authorizedRequest($settings)
            ->get('/v2/bills/' . $number . '/download-xml/')
            ->throw(fn ($response) => new FactusApiException(
                'No se pudo descargar el XML de la factura electrónica.',
                ['response' => $response->json() ?: $response->body()],
                $response->status()
            ))
            ->json();
    }

    public function listNumberingRanges(ElectronicInvoiceSetting $settings, array $filters = []): array
    {
        return $this->authorizedRequest($settings)
            ->get('/v2/numbering-ranges', $filters)
            ->throw(fn ($response) => new FactusApiException(
                'No se pudieron consultar los rangos de numeración.',
                ['response' => $response->json() ?: $response->body()],
                $response->status()
            ))
            ->json();
    }

    private function authorizedRequest(ElectronicInvoiceSetting $settings): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->timeout(config('factus.timeout'))
            ->connectTimeout(config('factus.connect_timeout'))
            ->withToken($this->accessToken($settings))
            ->baseUrl(rtrim($settings->baseUrl(), '/'));
    }

    private function accessToken(ElectronicInvoiceSetting $settings): string
    {
        $cacheKey = 'factus_token_' . sha1($settings->environment . '|' . $settings->username . '|' . $settings->client_id);

        return Cache::remember($cacheKey, now()->addSeconds(config('factus.token_cache_ttl')), function () use ($settings) {
            $auth = $this->authenticate($settings);

            return $auth['access_token'] ?? throw new FactusApiException('Factus no devolvió un access token válido.', ['response' => $auth]);
        });
    }
}
