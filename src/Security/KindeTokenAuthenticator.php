<?php

namespace Habityzer\KindeBundle\Security;

use Habityzer\KindeBundle\Service\KindeTokenValidator;
use Habityzer\KindeBundle\Service\KindeUserSync;
use Habityzer\KindeBundle\Service\KindeUserInfoService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Psr\Log\LoggerInterface;

/**
 * Authenticator for Kinde JWT tokens
 * Validates tokens and syncs users automatically
 */
class KindeTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator,
        private readonly KindeUserSync $userSync,
        private readonly KindeUserInfoService $userInfoService,
        private readonly LoggerInterface $logger,
        private readonly string $environment = 'prod'
    ) {}

    public function supports(Request $request): ?bool
    {
        // In test environment, don't use Kinde validation - let other authenticators handle it
        if ($this->environment === 'test') {
            return false;
        }
        
        // Check if Authorization header exists
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }
        
        // Don't process app tokens - let AppTokenAuthenticator handle them
        if (str_starts_with($authHeader, 'Bearer app_')) {
            return false;
        }
        
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        // Extract token from Authorization header
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('Invalid authorization header');
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        try {
            // Validate token with Kinde (SECURE - cryptographically verified)
            $payload = $this->tokenValidator->validateToken($token);
            
            // Extract user information from token
            $userInfo = $this->tokenValidator->extractUserInfo($payload);
            
            // If email is missing from token, fetch from Kinde UserInfo endpoint (SECURE)
            if (empty($userInfo['email'])) {
                $this->logger->info('Email missing from token, fetching from Kinde UserInfo endpoint');
                
                try {
                    // Make secure server-to-server call to Kinde
                    $kindeUserInfo = $this->userInfoService->getUserInfo($token);
                    $userInfoFromEndpoint = $this->userInfoService->extractUserData($kindeUserInfo);
                    
                    // Merge data (prefer UserInfo endpoint data for user details)
                    $userInfo = array_merge($userInfo, array_filter($userInfoFromEndpoint));
                    
                    $this->logger->info('Successfully fetched user info from Kinde', [
                        'email' => $userInfo['email'] ?? 'still missing'
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to fetch user info from Kinde UserInfo endpoint', [
                        'error' => $e->getMessage()
                    ]);
                    // Continue with token data only - will fail if email still missing
                }
            }
            
            // Sync user to database using app-provided user provider
            $user = $this->userSync->syncUser($userInfo);
            
            $this->logger->info('Kinde token authentication successful');
            
            // Return self-validating passport (token already validated)
            return new SelfValidatingPassport(
                new UserBadge($user->getUserIdentifier(), function () use ($user) {
                    return $user;
                })
            );
        } catch (\Exception $e) {
            $this->logger->warning('Kinde token authentication failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            throw new AuthenticationException('Token validation failed: ' . $e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Allow request to continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->error('Authentication failure', [
            'message' => $exception->getMessage(),
            'path' => $request->getPathInfo()
        ]);
        
        return new JsonResponse([
            'message' => 'Authentication failed',
            'error' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}

