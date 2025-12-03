# Advanced Usage Guide

This guide covers advanced scenarios, customization options, and best practices for the Habityzer Kinde Bundle.

## Table of Contents

- [Manual Token Validation](#manual-token-validation)
- [Email Fallback Mechanism](#email-fallback-mechanism)
- [JWKS Caching](#jwks-caching)
- [Multiple Authenticators](#multiple-authenticators)
- [Test Environment](#test-environment)
- [Custom Authentication Logic](#custom-authentication-logic)
- [Webhook Customization](#webhook-customization)
- [Performance Optimization](#performance-optimization)
- [Debugging Tips](#debugging-tips)

---

## Manual Token Validation

While the authenticator handles validation automatically, you may need to validate tokens manually in certain scenarios.

### Basic Manual Validation

```php
use Habityzer\KindeBundle\Service\KindeTokenValidator;
use Symfony\Component\HttpFoundation\JsonResponse;

class TokenController extends AbstractController
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator
    ) {}

    #[Route('/api/validate-token', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $token = $request->request->get('token');
        
        try {
            $payload = $this->tokenValidator->validateToken($token);
            
            return new JsonResponse([
                'valid' => true,
                'user_id' => $payload['sub'],
                'email' => $payload['email'] ?? null,
                'expires_at' => date('c', $payload['exp']),
            ]);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'valid' => false,
                'error' => $e->getMessage(),
            ], 401);
        }
    }
}
```

### Validate Without User Sync

For scenarios where you need to validate a token without syncing the user:

```php
class ApiGatewayController extends AbstractController
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator
    ) {}

    #[Route('/api/gateway/proxy', methods: ['GET', 'POST'])]
    public function proxy(Request $request): Response
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['error' => 'Missing token'], 401);
        }
        
        $token = substr($authHeader, 7);
        
        try {
            // Validate only - no user sync
            $payload = $this->tokenValidator->validateToken($token);
            
            // Forward to internal service with validated user info
            return $this->forwardRequest($request, $payload['sub']);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'Invalid token'], 401);
        }
    }
}
```

---

## Email Fallback Mechanism

The bundle implements a two-step email retrieval:

1. **Primary:** Extract email from JWT token claims
2. **Fallback:** Fetch from Kinde UserInfo endpoint if missing

### How It Works

```
Token received → Extract email from 'email' claim
                        ↓
                   Email found? 
                   ├─ Yes → Continue with email
                   └─ No → Call UserInfo endpoint
                            ↓
                       Merge user data
                            ↓
                       Continue authentication
```

### When Fallback Triggers

The fallback occurs when:
- `email` claim is missing from the token
- Token was issued without `email` scope
- Kinde is configured to exclude email from access tokens

### Manual UserInfo Fetch

```php
use Habityzer\KindeBundle\Service\KindeUserInfoService;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly KindeUserInfoService $userInfoService
    ) {}

    #[Route('/api/me/full-profile', methods: ['GET'])]
    public function fullProfile(Request $request): JsonResponse
    {
        $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        
        // Get complete profile from Kinde
        $userInfo = $this->userInfoService->getUserInfo($token);
        $normalized = $this->userInfoService->extractUserData($userInfo);
        
        return new JsonResponse($normalized);
    }
}
```

---

## JWKS Caching

The bundle caches JWKS (JSON Web Key Set) to avoid fetching keys on every request.

### Default Behavior

- Cache TTL: 3600 seconds (1 hour)
- Cache key: `kinde_jwks`
- Uses Symfony's cache system

### Configure Cache TTL

```yaml
# config/packages/habityzer_kinde.yaml
habityzer_kinde:
    jwks_cache_ttl: 7200  # Cache for 2 hours
```

### Force Cache Refresh

```php
use Habityzer\KindeBundle\Service\KindeTokenValidator;

class AdminController extends AbstractController
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator
    ) {}

    #[Route('/admin/kinde/refresh-keys', methods: ['POST'])]
    public function refreshKeys(): JsonResponse
    {
        $this->tokenValidator->clearJwksCache();
        
        return new JsonResponse(['message' => 'JWKS cache cleared']);
    }
}
```

### Create a Console Command

```php
<?php

namespace App\Command;

use Habityzer\KindeBundle\Service\KindeTokenValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kinde:refresh-jwks', description: 'Refresh Kinde JWKS cache')]
class RefreshJwksCommand extends Command
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->tokenValidator->clearJwksCache();
        $output->writeln('<info>JWKS cache cleared successfully</info>');
        
        return Command::SUCCESS;
    }
}
```

---

## Multiple Authenticators

The bundle's authenticator can coexist with other authenticators.

### Kinde Token + Custom Token

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            custom_authenticators:
                - Habityzer\KindeBundle\Security\KindeTokenAuthenticator
                - App\Security\CustomTokenAuthenticator
            entry_point: Habityzer\KindeBundle\Security\KindeTokenAuthenticator
```

### How Token Routing Works

The `KindeTokenAuthenticator` **only processes tokens with the `kinde_` prefix**:

```php
// In KindeTokenAuthenticator::supports()
// Extract token (remove "Bearer " prefix)
$token = substr($authHeader, 7);

// Only process tokens with kinde_ prefix
if (!str_starts_with($token, 'kinde_')) {
    $this->logger->debug('Not supporting request - token does not start with kinde_ prefix');
    return false; // Let other authenticators handle it
}
```

**Token Format:**
- **Kinde tokens:** `Authorization: Bearer kinde_eyJhbGciOi...` → Processed by KindeTokenAuthenticator
- **Custom tokens:** `Authorization: Bearer app_xyz123` → Ignored by KindeTokenAuthenticator

### Custom App Token Authenticator

Here's an example authenticator for custom app tokens:

```php
<?php

namespace App\Security;

use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
// ... other imports

class CustomTokenAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }
        
        $token = substr($authHeader, 7);
        
        // Only handle tokens with your custom prefix (or without kinde_ prefix)
        return str_starts_with($token, 'app_');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        $token = substr($authHeader, 7); // Remove "Bearer "
        $token = substr($token, 4); // Remove "app_" prefix
        
        // Your app token validation logic
        $apiToken = $this->apiTokenRepository->findOneBy(['token' => $token]);
        
        if (!$apiToken || !$apiToken->isValid()) {
            throw new AuthenticationException('Invalid app token');
        }
        
        return new SelfValidatingPassport(
            new UserBadge($apiToken->getUser()->getUserIdentifier())
        );
    }
    
    // ... other methods
}
```

### Token Examples

```bash
# Kinde token - handled by KindeTokenAuthenticator
curl -H "Authorization: Bearer kinde_eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..." \
     https://your-api.com/api/users

# Custom app token - handled by CustomTokenAuthenticator  
curl -H "Authorization: Bearer app_xyz123abc456def789" \
     https://your-api.com/api/users

# Unknown token format - returns 401
curl -H "Authorization: Bearer random_token_without_prefix" \
     https://your-api.com/api/users
```

---

## Test Environment

The authenticator is automatically disabled in the `test` environment.

### Why?

- Tests shouldn't depend on external services
- Faster test execution
- Easier to mock authentication

### How It Works

```php
// In KindeTokenAuthenticator::supports()
if ($this->environment === 'test') {
    return false;
}
```

### Testing with Mock Authentication

Create a test authenticator:

```php
<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
// ... imports

class TestAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly string $environment
    ) {}

    public function supports(Request $request): ?bool
    {
        // Only in test environment
        return $this->environment === 'test' 
            && $request->headers->has('X-Test-User');
    }

    public function authenticate(Request $request): Passport
    {
        $userId = $request->headers->get('X-Test-User');
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            throw new AuthenticationException('Test user not found');
        }
        
        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier())
        );
    }
    
    // ... other methods
}
```

Configure for test environment:

```yaml
# config/packages/test/security.yaml
security:
    firewalls:
        api:
            custom_authenticators:
                - App\Security\TestAuthenticator
```

### Functional Test Example

```php
<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTest extends WebTestCase
{
    public function testProtectedEndpoint(): void
    {
        $client = static::createClient();
        
        // Create test user
        $user = $this->createTestUser();
        
        // Make authenticated request
        $client->request('GET', '/api/me', [], [], [
            'HTTP_X_TEST_USER' => $user->getId(),
        ]);
        
        $this->assertResponseIsSuccessful();
    }
}
```

---

## Custom Authentication Logic

### Extend the Authenticator

```php
<?php

namespace App\Security;

use Habityzer\KindeBundle\Security\KindeTokenAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class CustomKindeAuthenticator extends KindeTokenAuthenticator
{
    public function authenticate(Request $request): Passport
    {
        // Call parent authentication
        $passport = parent::authenticate($request);
        
        // Add custom badges or logic
        $passport->addBadge(new CustomBadge());
        
        return $passport;
    }
    
    public function supports(Request $request): ?bool
    {
        // Add custom conditions
        if ($this->isMaintenanceMode()) {
            return false;
        }
        
        return parent::supports($request);
    }
}
```

### Register Custom Authenticator

```yaml
# config/services.yaml
services:
    App\Security\CustomKindeAuthenticator:
        decorates: Habityzer\KindeBundle\Security\KindeTokenAuthenticator
        arguments:
            $tokenValidator: '@Habityzer\KindeBundle\Service\KindeTokenValidator'
            $userSync: '@Habityzer\KindeBundle\Service\KindeUserSync'
            $userInfoService: '@Habityzer\KindeBundle\Service\KindeUserInfoService'
            $logger: '@logger'
            $environment: '%kernel.environment%'
```

---

## Webhook Customization

### Disable Auto-Registered Route

```yaml
# config/packages/habityzer_kinde.yaml
habityzer_kinde:
    enable_webhook_route: false
```

### Create Custom Webhook Controller

```php
<?php

namespace App\Controller;

use Habityzer\KindeBundle\Controller\KindeWebhookController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CustomWebhookController extends KindeWebhookController
{
    #[Route('/webhooks/kinde', name: 'custom_kinde_webhook', methods: ['POST'])]
    public function kindeWebhook(Request $request): JsonResponse
    {
        // Add custom pre-processing
        $this->logWebhookReceived($request);
        
        // Call parent handler
        $response = parent::kindeWebhook($request);
        
        // Add custom post-processing
        $this->notifyMonitoring($response);
        
        return $response;
    }
    
    private function logWebhookReceived(Request $request): void
    {
        // Custom logging logic
    }
    
    private function notifyMonitoring(JsonResponse $response): void
    {
        // Send to monitoring service
    }
}
```

### Add Custom Event Listeners

```php
<?php

namespace App\EventListener;

use Habityzer\KindeBundle\Event\KindeUserUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'kinde.user.updated', priority: 100)]
class HighPriorityUserListener
{
    public function __invoke(KindeUserUpdatedEvent $event): void
    {
        // Runs before other listeners
        $this->auditLog->record('user_updated', $event->getKindeId());
    }
}

#[AsEventListener(event: 'kinde.user.updated', priority: -100)]
class LowPriorityUserListener
{
    public function __invoke(KindeUserUpdatedEvent $event): void
    {
        // Runs after other listeners
        $this->cache->invalidate('user_' . $event->getKindeId());
    }
}
```

---

## Performance Optimization

### 1. Optimize JWKS Cache

Increase cache TTL for production:

```yaml
# config/packages/prod/habityzer_kinde.yaml
habityzer_kinde:
    jwks_cache_ttl: 86400  # 24 hours in production
```

### 2. Use Redis for Caching

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            kinde.jwks.cache:
                adapter: cache.adapter.redis
                provider: 'redis://localhost'
```

### 3. Minimize UserInfo Calls

Ensure tokens include email to avoid UserInfo fallback:

1. Configure `email` scope in frontend
2. Enable "Include email in token" in Kinde dashboard

### 4. Async Webhook Processing

For high-volume webhooks, process asynchronously:

```php
use Symfony\Component\Messenger\MessageBusInterface;

class AsyncWebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KindeEvents::SUBSCRIPTION_UPDATED => 'onSubscriptionUpdated',
        ];
    }

    public function onSubscriptionUpdated(KindeSubscriptionUpdatedEvent $event): void
    {
        // Dispatch to queue for async processing
        $this->bus->dispatch(new ProcessSubscriptionMessage(
            $event->getUserId(),
            $event->getPlanName(),
            $event->getData()
        ));
    }
}
```

---

## Debugging Tips

### 1. Debug Token Claims

```bash
php bin/console kinde:debug-token YOUR_TOKEN
```

### 2. Enable Detailed Logging

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        kinde:
            type: stream
            path: "%kernel.logs_dir%/kinde.log"
            level: debug
            channels: [security]
```

### 3. Test JWKS Endpoint

```bash
curl https://your-business.kinde.com/.well-known/jwks.json
```

### 4. Verify Webhook Signature Manually

```php
$body = file_get_contents('php://input');
$signature = str_replace('sha256=', '', $_SERVER['HTTP_KINDE_SIGNATURE']);
$expected = hash_hmac('sha256', $body, $webhookSecret);

var_dump([
    'received' => $signature,
    'expected' => $expected,
    'valid' => hash_equals($expected, $signature),
]);
```

### 5. Log Raw Webhook Payloads

```php
public function onUserUpdated(KindeUserUpdatedEvent $event): void
{
    $this->logger->debug('Kinde webhook payload', [
        'event' => 'user.updated',
        'data' => $event->getData(),
    ]);
}
```

### 6. Common Issues Checklist

- [ ] Environment variables are set correctly
- [ ] No trailing slashes in `KINDE_DOMAIN`
- [ ] `KINDE_CLIENT_ID` matches token audience
- [ ] Webhook secret matches Kinde dashboard
- [ ] Cache is cleared after config changes
- [ ] Frontend requests correct scopes
- [ ] Webhook endpoint is publicly accessible

---

## Next Steps

- [Events Reference](EVENTS.md) - Complete event documentation
- [Services API](SERVICES.md) - Service methods reference
- [Kinde Setup](KINDE_SETUP.md) - Dashboard configuration
- [Installation Guide](../INSTALL.md) - Step-by-step setup


