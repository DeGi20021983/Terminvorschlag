<?php

namespace Tests\Feature\Security;

use App\Directory\DirectoryService;
use Tests\Support\FakeDirectoryService;
use Tests\TestCase;

class TransportAndNetworkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $directory = (new FakeDirectoryService)->add('anna.schmidt');
        $this->app->instance(DirectoryService::class, $directory);
    }

    public function test_plain_http_is_redirected_to_https_when_forced(): void
    {
        config(['terminvorschlag.force_https' => true]);

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\anna.schmidt'])
            ->get('http://localhost/')
            ->assertStatus(308)
            ->assertRedirect('https://localhost/');
    }

    public function test_request_from_outside_the_allowed_network_is_forbidden(): void
    {
        config([
            'terminvorschlag.force_https' => false,
            'terminvorschlag.allowed_networks' => ['10.0.0.0/8'],
        ]);

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\anna.schmidt'])
            ->get('/angebote')
            ->assertForbidden();
    }

    public function test_request_from_allowed_network_passes(): void
    {
        config([
            'terminvorschlag.force_https' => false,
            'terminvorschlag.allowed_networks' => ['127.0.0.0/8'],
        ]);

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\anna.schmidt'])
            ->get('/angebote')
            ->assertOk();
    }

    public function test_responses_send_security_headers_and_no_cache(): void
    {
        config(['terminvorschlag.force_https' => false]);

        $response = $this->withServerVariables(['REMOTE_USER' => 'CORP\\anna.schmidt'])->get('/angebote');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
