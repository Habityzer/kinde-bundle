<?php

namespace Habityzer\KindeBundle\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates Kinde JWT tokens using JWKS
 * This service is fully reusable and has no business logic
 */
class KindeTokenValidator
{
    private string $kindeJwksUrl;
    private string $kindeClientId;
    private string $kindeDomain;
    private int $jwksCacheTtl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        string $kindeDomain,
        string $kindeClientId,
        int $jwksCacheTtl = 3600
    ) {
        // Validate configuration is properly set (not default placeholder values)
        if ($kindeDomain === 'your-business.kinde.com' || empty($kindeDomain)) {
            throw new \RuntimeException(
                'Kinde domain is not configured. Please set KINDE_DOMAIN in your .env file. ' .
                'Get your domain from https://app.kinde.com/settings/environment'
            );
        }
        
        if ($kindeClientId === 'your-kinde-client-id' || empty($kindeClientId)) {
            throw new \RuntimeException(
                'Kinde client ID is not configured. Please set KINDE_CLIENT_ID in your .env file. ' .
                'Get your client ID from https://app.kinde.com/settings/applications'
            );
        }
        
        $this->kindeDomain = rtrim($kindeDomain, '/');
        $this->kindeJwksUrl = $this->kindeDomain . '/.well-known/jwks.json';
        $this->kindeClientId = $kindeClientId;
        $this->jwksCacheTtl = $jwksCacheTtl;
    }

    /**
     * Validate Kinde JWT token and return decoded payload
     * 
     * @throws \RuntimeException if token is invalid
     */
    public function validateToken(string $token): array
    {
        try {
            // Get JWKS (cached)
            $jwks = $this->getJwks();
            
            // Parse JWKS into Key objects
            $keys = JWK::parseKeySet($jwks);
            
            // Decode and validate token
            $decoded = JWT::decode($token, $keys);
            
            // Convert to array
            $payload = (array) $decoded;
            
            // Additional validation
            $this->validateAudience($payload);
            $this->validateIssuer($payload);
            
            $this->logger->info('Successfully validated Kinde token', [
                'sub' => $payload['sub'] ?? null,
                'email' => $payload['email'] ?? null
            ]);
            
            return $payload;
            
        } catch (\Exception $e) {
            $this->logger->error('Token validation failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            throw new \RuntimeException('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * Get JWKS from Kinde (with caching)
     */
    private function getJwks(): array
    {
        return $this->cache->get('kinde_jwks', function (ItemInterface $item) {
            $item->expiresAfter($this->jwksCacheTtl);
            
            try {
                $response = $this->httpClient->request('GET', $this->kindeJwksUrl);
                
                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException('Failed to fetch JWKS from Kinde');
                }
                
                $jwks = $response->toArray();
                
                $this->logger->info('Fetched JWKS from Kinde', [
                    'url' => $this->kindeJwksUrl,
                    'keys_count' => count($jwks['keys'] ?? [])
                ]);
                
                return $jwks;
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to fetch JWKS', [
                    'url' => $this->kindeJwksUrl,
                    'error' => $e->getMessage()
                ]);
                throw new \RuntimeException('Unable to fetch JWKS: ' . $e->getMessage());
            }
        });
    }

    /**
     * Validate token audience - accept either 'aud' or 'azp' claim
     */
    private function validateAudience(array $payload): void
    {
        // Kinde may use 'azp' (authorized party) instead of 'aud' for M2M tokens
        $audience = null;
        
        // Check 'aud' claim first
        if (isset($payload['aud']) && !empty($payload['aud'])) {
            $audiences = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];
            $audience = $audiences[0] ?? null;
        }
        
        // Fallback to 'azp' claim if aud is empty
        if (!$audience && isset($payload['azp'])) {
            $audience = $payload['azp'];
            $this->logger->info('Using azp claim for audience validation', [
                'azp' => $audience
            ]);
        }
        
        if (!$audience) {
            throw new \RuntimeException('Token missing both audience (aud) and authorized party (azp) claims');
        }
        
        // Validate against expected client ID
        if ($audience !== $this->kindeClientId) {
            $this->logger->warning('Token audience mismatch', [
                'expected' => $this->kindeClientId,
                'actual' => $audience,
                'claim_used' => isset($payload['azp']) ? 'azp' : 'aud'
            ]);
            throw new \RuntimeException(
                'Invalid token audience. Expected: ' . $this->kindeClientId . 
                ', Got: ' . $audience
            );
        }
    }

    /**
     * Validate token issuer (must be from Kinde)
     */
    private function validateIssuer(array $payload): void
    {
        if (!isset($payload['iss'])) {
            throw new \RuntimeException('Token missing issuer claim');
        }
        
        // Issuer should match the Kinde domain
        $expectedIssuer = $this->kindeDomain;
        
        if ($payload['iss'] !== $expectedIssuer) {
            $this->logger->warning('Token issuer mismatch', [
                'expected' => $expectedIssuer,
                'actual' => $payload['iss']
            ]);
            throw new \RuntimeException('Invalid token issuer');
        }
    }

    /**
     * Extract user information from token payload
     */
    public function extractUserInfo(array $payload): array
    {
        // Get email from token (SECURE - cryptographically signed by Kinde)
        $email = $payload['email'] ?? null;
        
        // Log detailed info if email is missing
        if (!$email) {
            $this->logger->error('Email missing from Kinde token. Token must include email claim!', [
                'available_claims' => array_keys($payload),
                'sub' => $payload['sub'] ?? 'unknown',
                'fix' => 'Add scope "openid profile email" in Kinde SDK config or use ID token instead of access token'
            ]);
        }
        
        return [
            'kinde_id' => $payload['sub'] ?? null,
            'email' => $email,
            'given_name' => $payload['given_name'] ?? null,
            'family_name' => $payload['family_name'] ?? null,
            'name' => $payload['name'] ?? null,
            'picture' => $payload['picture'] ?? null,
            'organization_code' => $payload['org_code'] ?? null,
        ];
    }
    
    /**
     * Clear JWKS cache (useful for debugging or forced refresh)
     */
    public function clearJwksCache(): void
    {
        $this->cache->delete('kinde_jwks');
        $this->logger->info('Cleared JWKS cache');
    }
}

