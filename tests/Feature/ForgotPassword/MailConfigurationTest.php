<?php

namespace Tests\Feature\ForgotPassword;

use App\Services\AzureEmailService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    public function test_mail_and_azure_email_configuration_keys_are_defined(): void
    {
        $this->assertContains(config('mail.default'), ['log', 'array', 'smtp']);
        $this->assertIsArray(config('services.azure_communication'));
        $this->assertArrayHasKey('endpoint', config('services.azure_communication'));
        $this->assertArrayHasKey('access_key', config('services.azure_communication'));
        $this->assertArrayHasKey('sender', config('services.azure_communication'));
    }

    public function test_azure_email_service_sends_signed_request_successfully(): void
    {
        config()->set('services.azure_communication.endpoint', 'https://example-comm.communication.azure.com');
        config()->set('services.azure_communication.access_key', base64_encode('test-secret-key-for-azure-hmac'));
        config()->set('services.azure_communication.sender', 'DoNotReply@example.com');

        Http::fake([
            'https://example-comm.communication.azure.com/emails:send*' => Http::response(
                ['id' => 'op-123', 'status' => 'Running'],
                202,
                ['Operation-Location' => 'https://example-comm.communication.azure.com/emails/operations/op-123']
            ),
        ]);

        $service = app(AzureEmailService::class);
        $result = $service->send(
            'recipient@example.com',
            'Código para recuperar tu contraseña',
            '<p>Tu código es 123456</p>'
        );

        $this->assertSame(202, $result['status']);
        $this->assertSame('op-123', $result['data']['id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/emails:send?api-version=2023-03-31')
                && $request->hasHeader('Authorization')
                && $request->hasHeader('x-ms-content-sha256')
                && $request['senderAddress'] === 'DoNotReply@example.com'
                && $request['recipients']['to'][0]['address'] === 'recipient@example.com';
        });
    }
}

