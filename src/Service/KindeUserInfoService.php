<?php

namespace Habityzer\KindeBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service to fetch user information from Kinde's UserInfo endpoint
 * This is used when the JWT token doesn't contain email claim
 * Fully reusable - no business logic
 */
class KindeUserInfoService
{
    private string $kindeUserInfoUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $kindeDomain
    ) {
        $this->kindeUserInfoUrl = rtrim($kindeDomain, '/') . '/oauth2/v2/user_profile';
    }

    /**
     * Fetch user information from Kinde using the access token
     * This is a secure server-to-server call
     * 
     * @param string $accessToken The validated access token
     * @return array User information including email
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $this->logger->info('Fetching user info from Kinde UserInfo endpoint');
            
            $response = $this->httpClient->request('GET', $this->kindeUserInfoUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Failed to fetch user info from Kinde');
            }

            $userInfo = $response->toArray();
            
            $this->logger->info('Successfully fetched user info from Kinde', [
                'sub' => $userInfo['sub'] ?? 'unknown',
                'email' => $userInfo['email'] ?? 'missing'
            ]);

            return $userInfo;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch user info from Kinde', [
                'url' => $this->kindeUserInfoUrl,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Unable to fetch user info: ' . $e->getMessage());
        }
    }

    /**
     * Extract user data in standard format
     */
    public function extractUserData(array $userInfo): array
    {
        return [
            'kinde_id' => $userInfo['sub'] ?? null,
            'email' => $userInfo['email'] ?? null,
            'given_name' => $userInfo['given_name'] ?? null,
            'family_name' => $userInfo['family_name'] ?? null,
            'name' => $userInfo['name'] ?? null,
            'picture' => $userInfo['picture'] ?? null,
            'organization_code' => $userInfo['org_code'] ?? null,
        ];
    }
}

