<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Services\Contracts\GeminiImageServiceInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Real Gemini image-editing client. Sends the deterministic overlay PNG as an
 * inline image, asks the model to enhance realism while preserving panel
 * geometry, and returns the first image found in the response.
 */
final class GoogleGeminiImageService implements GeminiImageServiceInterface
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function enhance(string $overlayPng, string $prompt): array
    {
        $key = (string) config('solar.gemini.api_key');
        if ($key === '') {
            throw new RuntimeException('GEMINI_API_KEY missing; set FAKE_PROVIDERS=true for offline mode.');
        }

        $model = (string) config('solar.gemini.model', 'gemini-2.5-flash-image');
        $endpoint = rtrim((string) config('solar.gemini.endpoint'), '/').'/models/'.$model.':generateContent';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => [
                        'mime_type' => 'image/png',
                        'data' => base64_encode($overlayPng),
                    ]],
                ],
            ]],
        ];

        $resp = $this->http
            ->timeout(90)
            ->withHeaders(['x-goog-api-key' => $key])
            ->post($endpoint, $payload);

        if (!$resp->ok()) {
            $snippet = substr((string) $resp->body(), 0, 300);
            $snippet = preg_replace('/\s+/', ' ', $snippet) ?? '';
            throw new RuntimeException('Gemini image request failed: HTTP '.$resp->status().': '.$snippet);
        }

        $body = $resp->json();
        $parts = $body['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            $data = $part['inline_data']['data'] ?? $part['inlineData']['data'] ?? null;
            if (is_string($data) && $data !== '') {
                $decoded = base64_decode($data, true);
                if ($decoded !== false) {
                    return [
                        'bytes' => $decoded,
                        'notes' => "renderer: gemini\nmodel: {$model}\nbytes: ".strlen($decoded)."\n",
                    ];
                }
            }
        }

        throw new RuntimeException('Gemini image response contained no inline_data image.');
    }
}
