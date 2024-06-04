<?php

namespace CommunityTranslation\Api\Jwt;

use Concrete\Core\Cache\Level\ExpensiveCache;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerAwareInterface;
use Concrete\Core\Logging\LoggerAwareTrait;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Rsa\Sha384;
use Lcobucci\JWT\Signer\Rsa\Sha512;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\SignedWith as JwtSignedWith;
use Lcobucci\JWT\Validation\ConstraintViolation;
use Lcobucci\JWT\Validation\SignedWith;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Math\BigInteger;
use Psr\Http\Message\ResponseInterface;
use Stash\Interfaces\ItemInterface;

class SignedWithOidc implements SignedWith, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected ExpensiveCache $cache,
        protected Client $client,
    ) {}

    public function assert(Token $token): void
    {
        $issuer = (string) $token->claims()->get('iss');
        $kid = (string) $token->headers()->get('kid');
        $algo = (string) $token->headers()->get('alg');

        // Make sure we have an authorized issuer
        match (true) {
            $issuer === '' => throw new ConstraintViolation('Token missing iss claim'),
            $kid === '' => throw new ConstraintViolation('Token missing kid header'),
            $algo === '' => throw new ConstraintViolation('Token missing alg header'),
            default => null,
        };

        if (!preg_match($_ENV['MARKETPLACE_ISSUER_REGEX'] ?? '~^https://market.concretecms.org/?$~', $issuer)) {
            throw new ConstraintViolation('Access denied, invalid issuer');
        }

        // Make sure the key is signed with a valid market key, is valid now, and is permitted for us
        $key = $this->getKey($issuer, $kid);

        $signer = match ($algo) {
            'RS256' => new Sha256(),
            'RS384' => new Sha384(),
            'RS512' => new Sha512(),
            default => throw new ConstraintViolation(sprintf(
                'Access denied, unsupported token algorithm "%s"',
                preg_replace('/[^[:alnum:]]/', '', $algo)
            )),
        };

        // Assert the given token is signed with the expected key
        (new JwtSignedWith($signer, InMemory::plainText($key)))->assert($token);
    }

    public function getLoggerChannel(): string
    {
        return Channels::CHANNEL_API;
    }

    protected function getKey(string $issuer, string $kid): ?string
    {
        $jwks = $this->getJwkUri($issuer);
        if (!$jwks) {
            return null;
        }

        $issuerKey = hash('sha256', $issuer);
        $cacheKey = "ct.oidc.{$issuerKey}.jwk";
        $cacheItem = $this->cache->getItem($cacheKey);

        try {
            if ($cacheItem->isHit()) {
                $json = $cacheItem->get();
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } else {
                $response = $this->client->get($jwks, [RequestOptions::HEADERS => ['Accept' => 'application/json']]);
                if ($response->getStatusCode() !== 200) {
                    return null;
                }

                $json = $response->getBody()->getContents();
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                $this->cacheResponse($cacheItem, $json, $response);
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('Unable to load OIDC JWKs: ' . $e->getMessage());

            return null;
        }

        foreach ($data['keys'] ?? [] as $key) {
            if ($key['kid'] === $kid) {
                $e = base64_decode($key['e'] ?? '');
                $n = base64_decode(strtr($key['n'] ?? '', '-_', '+/'), true);

                if ($e === '' || $n === '') {
                    $this->logger?->warning('Unable to load OIDC JWK, invalid base64');

                    return null;
                }

                try {
                    return PublicKeyLoader::load([
                        'e' => new BigInteger($e, 256),
                        'n' => new BigInteger($n, 256),
                    ]);
                } catch (\Throwable $e) {
                    $this->logger?->warning('Unable to load OIDC key: ' . $e->getMessage());

                    return null;
                }
            }
        }

        return null;
    }

    private function getJwkUri(string $issuer): ?string
    {
        $issuerKey = hash('sha256', $issuer);
        $cacheKey = "ct.oidc.{$issuerKey}";

        $cacheItem = $this->cache->getItem($cacheKey);

        try {
            if ($cacheItem->isHit()) {
                $json = $cacheItem->get();
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } else {
                $metadataUri = rtrim($issuer, '/') . '/.well-known/openid-configuration';
                $response = $this->client->get(
                    $metadataUri,
                    [RequestOptions::HEADERS => ['Accept' => 'application/json']]
                );
                if ($response->getStatusCode() !== 200) {
                    return null;
                }

                $json = $response->getBody()->getContents();
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                $this->cacheResponse($cacheItem, $json, $response);
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('Unable to load OIDC configuration: ' . $e->getMessage());

            return null;
        }

        return $data['jwks_uri'] ?? null;
    }

    private function cacheResponse(ItemInterface $cacheItem, string $json, ResponseInterface $response): void
    {
        preg_match('/max-age=(\d+)/', $response->getHeader('Cache-Control')[0] ?? '', $matches);
        $duration = (int) ($matches[1] ?? 0);

        if ($duration > 0) {
            $cacheItem->set($json);
            $cacheItem->setTTL($duration);
            $cacheItem->save();
        }
    }
}
