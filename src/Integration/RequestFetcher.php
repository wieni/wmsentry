<?php

namespace Drupal\wmsentry\Integration;

use Http\Discovery\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Sentry\Integration\RequestFetcherInterface;

/**
 * Sentry's own request fetcher, without the withPort() deprecation.
 *
 * From sentry/sentry 3.17 to 3.22, \Sentry\Integration\RequestFetcher uses
 * php-http/discovery's Psr17Factory, which passes $_SERVER['SERVER_PORT'] as a
 * string to UriInterface::withPort(). guzzlehttp/psr7 2.11 deprecates that.
 */
class RequestFetcher implements RequestFetcherInterface
{
    public function fetchRequest(): ?ServerRequestInterface
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || \PHP_SAPI === 'cli') {
            return null;
        }

        $factory = new class() extends Psr17Factory {
            public function createUriFromGlobals(?array $server = null): UriInterface
            {
                $server = $server ?? $_SERVER;
                $port = $server['SERVER_PORT'] ?? null;

                // Same value: the PSR-7 implementations cast it to an int too.
                if (is_string($port) && preg_match('/^[+-]?\d+$/D', $port)) {
                    $server['SERVER_PORT'] = (int) $port;
                }

                return parent::createUriFromGlobals($server);
            }
        };

        return $factory->createServerRequestFromGlobals();
    }
}
