<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AzureEmailService
{
    public function send(
        string $to,
        string $subject,
        string $html,
        ?string $plainText = null
    ): array {
        $endpoint = rtrim(
            config('services.azure_communication.endpoint'),
            '/'
        );

        $accessKey = config(
            'services.azure_communication.access_key'
        );

        $sender = config(
            'services.azure_communication.sender'
        );

        $pathAndQuery = '/emails:send?api-version=2023-03-31';

        $url = $endpoint . $pathAndQuery;

        $body = [
            'senderAddress' => $sender,

            'recipients' => [
                'to' => [
                    [
                        'address' => $to,
                    ],
                ],
            ],

            'content' => [
                'subject' => $subject,
                'plainText' => $plainText ?? strip_tags($html),
                'html' => $html,
            ],
        ];

        $bodyJson = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($bodyJson === false) {
            throw new \Exception(
                'No fue posible convertir el correo a JSON.'
            );
        }

        $contentHash = base64_encode(
            hash(
                'sha256',
                $bodyJson,
                true
            )
        );

        $date = gmdate('D, d M Y H:i:s') . ' GMT';


        $host = parse_url($endpoint, PHP_URL_HOST);

        $stringToSign =
            "POST\n" .
            $pathAndQuery . "\n" .
            $date . ";" .
            $host . ";" .
            $contentHash;

        $decodedKey = base64_decode(
            $accessKey,
            true
        );

        if ($decodedKey === false) {
            throw new \Exception(
                'AZURE_COMMUNICATION_ACCESS_KEY no es una clave Base64 válida.'
            );
        }


        $signature = base64_encode(
            hash_hmac(
                'sha256',
                $stringToSign,
                $decodedKey,
                true
            )
        );

        $authorization =
            'HMAC-SHA256 ' .
            'SignedHeaders=x-ms-date;host;x-ms-content-sha256' .
            '&Signature=' .
            $signature;


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-ms-date' => $date,
            'x-ms-content-sha256' => $contentHash,
            'Authorization' => $authorization,
            'x-ms-client-request-id' => (string) Str::uuid(),
        ])->withBody(
            $bodyJson,
            'application/json'
        )->post($url);

        if ($response->failed()) {
            throw new \Exception(
                'Error enviando correo mediante Azure: ' .
                $response->status() .
                ' - ' .
                $response->body()
            );
        }

        return [
            'status' => $response->status(),
            'data' => $response->json(),
            'operation_location' =>
                $response->header('Operation-Location'),
        ];
    }
}
