<?php

namespace Tests\Unit\Payments\Nmb;

use App\Payments\Gateways\Nmb\NmbApiException;
use App\Payments\Gateways\Nmb\NmbHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NmbHttpClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nmb.http.timeout' => 30,
            'services.nmb.http.connect_timeout' => 10,
            'services.nmb.http.retry_times' => 2,
        ]);
    }

    public function test_get_retries_on_connection_failure(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('Connection timed out.');
        });

        $client = app(NmbHttpClient::class);

        try {
            $client->get('https://sandbox.nmb.test/order/123', fn ($request) => $request);
            $this->fail('Expected NmbApiException was not thrown.');
        } catch (NmbApiException $exception) {
            $this->assertTrue($exception->isTransient());
            $this->assertSame('Unable to reach NMB API.', $exception->getMessage());
        }

        $this->assertSame(3, $attempts);
    }

    public function test_get_treats_503_as_transient(): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response(['error' => ['explanation' => 'Unavailable']], 503),
        ]);

        $client = app(NmbHttpClient::class);

        try {
            $client->get('https://sandbox.nmb.test/order/123', fn ($request) => $request);
            $this->fail('Expected NmbApiException was not thrown.');
        } catch (NmbApiException $exception) {
            $this->assertTrue($exception->isTransient());
            $this->assertSame(503, $exception->statusCode());
        }
    }

    public function test_get_treats_400_as_permanent(): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response(['error' => ['explanation' => 'Bad request']], 400),
        ]);

        $client = app(NmbHttpClient::class);

        try {
            $client->get('https://sandbox.nmb.test/order/123', fn ($request) => $request);
            $this->fail('Expected NmbApiException was not thrown.');
        } catch (NmbApiException $exception) {
            $this->assertFalse($exception->isTransient());
            $this->assertSame(400, $exception->statusCode());
        }
    }

    public function test_post_does_not_retry_on_connection_failure(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('Connection timed out.');
        });

        $client = app(NmbHttpClient::class);

        try {
            $client->post('https://sandbox.nmb.test/session', ['apiOperation' => 'INITIATE_CHECKOUT'], fn ($request) => $request);
            $this->fail('Expected NmbApiException was not thrown.');
        } catch (NmbApiException $exception) {
            $this->assertTrue($exception->isTransient());
        }

        $this->assertSame(1, $attempts);
    }
}
