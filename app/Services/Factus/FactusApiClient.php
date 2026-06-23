<?php

namespace App\Services\Factus;

use App\Models\ElectronicInvoiceSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FactusApiClient
{
    public function authenticate(ElectronicInvoiceSetting $settings): array
    {
        if (!$settings->client_id || !$settings->client_secret || !$settings->username || !$settings->password) {
            throw new FactusApiException('La configuracion de Factus esta incompleta.');
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
            throw $this->exceptionFromResponse($response, 'No se pudo autenticar con Factus.');
        }

        return $response->json();
    }

    public function createBill(ElectronicInvoiceSetting $settings, array $payload): array
    {
        $response = $this->authorizedRequest($settings)
            ->post('/v2/bills/validate', $payload);

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response, 'Factus rechazo la factura electronica.');
        }

        return $response->json();
    }

    public function getBill(ElectronicInvoiceSetting $settings, string $number): array
    {
        $response = $this->authorizedRequest($settings)
            ->get('/v2/bills/' . $number);

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response, 'No se pudo consultar la factura en Factus.');
        }

        return $response->json();
    }

    public function listBills(ElectronicInvoiceSetting $settings, array $filters = []): array
    {
        $response = $this->authorizedRequest($settings)
            ->get('/v2/bills', $filters);

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response, 'No se pudieron consultar las facturas en Factus.');
        }

        return $response->json();
    }

    public function downloadPdf(ElectronicInvoiceSetting $settings, string $number): array
    {
        $response = $this->authorizedRequest($settings)
            ->get('/v2/bills/' . $number . '/download-pdf');

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response, 'No se pudo descargar el PDF de la factura electronica.');
        }

        return $response->json();
    }

    public function downloadXml(ElectronicInvoiceSetting $settings, string $number): array
    {
        $response = $this->authorizedRequest($settings)
            ->get('/v2/bills/' . $number . '/download-xml/');

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response, 'No se pudo descargar el XML de la factura electronica.');
        }

        return $response->json();
    }

    public function listNumberingRanges(ElectronicInvoiceSetting $settings, array $filters = []): array
    {
        $response = $this->authorizedRequest($settings)
            ->get('/v2/numbering-ranges', $filters);

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response, 'No se pudieron consultar los rangos de numeracion.');
        }

        return $response->json();
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

            return $auth['access_token'] ?? throw new FactusApiException('Factus no devolvio un access token valido.', ['response' => $auth]);
        });
    }

    private function exceptionFromResponse(Response $response, string $fallbackMessage): FactusApiException
    {
        $body = $response->json() ?: $response->body();
        $message = is_array($body) ? (string) ($body['message'] ?? $fallbackMessage) : $fallbackMessage;

        if ($response->status() === 403 && str_contains(strtolower($message), 'version de api no disponible')) {
            $message = 'Factus autentico las credenciales, pero esta empresa no tiene habilitada la API v2. Solicita a Factus habilitar la version de API para tu empresa o entregar credenciales sandbox con permisos de facturacion electronica.';
        }

        return new FactusApiException($message, ['response' => $body], $response->status());
    }
}
