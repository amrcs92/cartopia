<?php

namespace Tests\Support;

use RuntimeException;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;

/**
 * Stands in for the Stripe SDK's HTTP client so tests never reach the network.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: list<string>, params: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<array{int, array<string, mixed>}|ApiConnectionException> */
    private array $queue = [];

    /**
     * @param  array<string, mixed>  $body
     */
    public function queue(int $status, array $body): static
    {
        $this->queue[] = [$status, $body];

        return $this;
    }

    public function queueConnectionError(): static
    {
        $this->queue[] = new ApiConnectionException('Could not connect to Stripe.');

        return $this;
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $params
     * @return array{string, int, array<string, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'headers' => $headers, 'params' => $params];

        $next = array_shift($this->queue) ?? throw new RuntimeException("Unexpected Stripe request: {$method} {$absUrl}");

        if ($next instanceof ApiConnectionException) {
            throw $next;
        }

        return [json_encode($next[1], JSON_THROW_ON_ERROR), $next[0], []];
    }

    /**
     * @return array{method: string, url: string, headers: list<string>, params: array<string, mixed>}
     */
    public function last(): array
    {
        return $this->requests[array_key_last($this->requests)] ?? throw new RuntimeException('No Stripe request was made.');
    }

    public function header(string $name): ?string
    {
        foreach ($this->last()['headers'] as $line) {
            if (str_starts_with(strtolower($line), strtolower($name).': ')) {
                return substr($line, strlen($name) + 2);
            }
        }

        return null;
    }
}
