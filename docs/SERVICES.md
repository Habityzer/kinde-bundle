# Services API Reference

This document provides complete documentation for all services provided by the Habityzer Kinde Bundle.

## Overview

The bundle provides four main services:

| Service | Purpose |
|---------|---------|
| `KindeTokenValidator` | Validates JWT tokens using Kinde's JWKS |
| `KindeUserInfoService` | Fetches user info from Kinde's UserInfo endpoint |
| `KindeUserSync` | Synchronizes users between Kinde and your database |
| `KindeTokenAuthenticator` | Symfony security authenticator |

All services are registered with autowiring and can be injected into your controllers and services.

---

## KindeTokenValidator

Validates Kinde JWT tokens using JSON Web Key Sets (JWKS) with automatic caching.

**Class:** `Habityzer\KindeBundle\Service\KindeTokenValidator`

### Methods

#### `validateToken(string $token): array`

Validates a JWT token and returns the decoded payload.

**Parameters:**
- `$token` - The JWT token string (without "Bearer " prefix)

**Returns:** `array` - The decoded token payload

**Throws:** `\RuntimeException` if token is invalid

```php
use Habityzer\KindeBundle\Service\KindeTokenValidator;

class MyController extends AbstractController
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator
    ) {}

    public function validateAction(Request $request): JsonResponse
    {
        $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        
        try {
            $payload = $this->tokenValidator->validateToken($token);
            
            return new JsonResponse([
                'valid' => true,
                'sub' => $payload['sub'],
                'email' => $payload['email'] ?? null,
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

---

#### `extractUserInfo(array $payload): array`

Extracts user information from a validated token payload into a standardized format.

**Parameters:**
- `$payload` - The decoded token payload from `validateToken()`

**Returns:** `array` with the following keys:
- `kinde_id` - User's Kinde ID (`sub` claim)
- `email` - User's email (may be null)
- `given_name` - First name
- `family_name` - Last name
- `name` - Full name
- `picture` - Profile picture URL
- `organization_code` - Organization code (`org_code` claim)

```php
$payload = $this->tokenValidator->validateToken($token);
$userInfo = $this->tokenValidator->extractUserInfo($payload);

// $userInfo example:
[
    'kinde_id' => 'kp_abc123...',
    'email' => 'user@example.com',
    'given_name' => 'John',
    'family_name' => 'Doe',
    'name' => 'John Doe',
    'picture' => 'https://...',
    'organization_code' => 'org_abc123',
]
```

---

#### `clearJwksCache(): void`

Clears the cached JWKS keys. Useful for debugging or when Kinde rotates keys.

```php
// Force refresh of JWKS keys
$this->tokenValidator->clearJwksCache();

// Next validation will fetch fresh keys from Kinde
$payload = $this->tokenValidator->validateToken($token);
```

**Use cases:**
- Debugging token validation issues
- After Kinde key rotation
- In admin commands for troubleshooting

---

## KindeUserInfoService

Fetches user information from Kinde's OAuth2 UserInfo endpoint. This is used as a fallback when the JWT token doesn't contain email or other profile claims.

**Class:** `Habityzer\KindeBundle\Service\KindeUserInfoService`

### Methods

#### `getUserInfo(string $accessToken): array`

Fetches user information from Kinde using the access token.

**Parameters:**
- `$accessToken` - A valid Kinde access token

**Returns:** `array` - Raw user info response from Kinde

**Throws:** `\RuntimeException` if the request fails

```php
use Habityzer\KindeBundle\Service\KindeUserInfoService;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly KindeUserInfoService $userInfoService
    ) {}

    public function getProfile(Request $request): JsonResponse
    {
        $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        
        try {
            $userInfo = $this->userInfoService->getUserInfo($token);
            
            return new JsonResponse($userInfo);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
```

**Example Response:**

```php
[
    'sub' => 'kp_abc123...',
    'email' => 'user@example.com',
    'email_verified' => true,
    'given_name' => 'John',
    'family_name' => 'Doe',
    'name' => 'John Doe',
    'picture' => 'https://...',
    'org_code' => 'org_abc123',
]
```

---

#### `extractUserData(array $userInfo): array`

Extracts user data into a standardized format.

**Parameters:**
- `$userInfo` - Raw response from `getUserInfo()`

**Returns:** `array` with standardized keys

```php
$rawInfo = $this->userInfoService->getUserInfo($token);
$userData = $this->userInfoService->extractUserData($rawInfo);

// $userData is now standardized:
[
    'kinde_id' => 'kp_abc123...',
    'email' => 'user@example.com',
    'given_name' => 'John',
    'family_name' => 'Doe',
    'name' => 'John Doe',
    'picture' => 'https://...',
    'organization_code' => 'org_abc123',
]
```

---

## KindeUserSync

Synchronizes users from Kinde to your local database using your `KindeUserProviderInterface` implementation.

**Class:** `Habityzer\KindeBundle\Service\KindeUserSync`

### Methods

#### `syncUser(array $userInfo): object`

Syncs a user from Kinde token data to your database. Creates a new user or updates an existing one.

**Parameters:**
- `$userInfo` - User information array (from token or UserInfo endpoint)

**Returns:** `object` - Your user entity

**Throws:** `\RuntimeException` if:
- Missing `kinde_id` in user info
- Missing `email` for new users

```php
use Habityzer\KindeBundle\Service\KindeUserSync;
use Habityzer\KindeBundle\Service\KindeTokenValidator;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator,
        private readonly KindeUserSync $userSync
    ) {}

    public function login(Request $request): JsonResponse
    {
        $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        
        // Validate token
        $payload = $this->tokenValidator->validateToken($token);
        $userInfo = $this->tokenValidator->extractUserInfo($payload);
        
        // Sync user to database
        $user = $this->userSync->syncUser($userInfo);
        
        return new JsonResponse([
            'message' => 'Logged in successfully',
            'user_id' => $user->getId(),
        ]);
    }
}
```

---

#### `findByKindeId(string $kindeId): ?object`

Finds a user by their Kinde ID.

**Parameters:**
- `$kindeId` - The Kinde user ID (sub claim)

**Returns:** `?object` - Your user entity or null if not found

```php
$user = $this->userSync->findByKindeId('kp_abc123...');

if ($user) {
    // User exists in database
} else {
    // User not found
}
```

---

#### `unlinkUser(object $user): void`

Unlinks a user from Kinde (for account deletion or unlinking).

**Parameters:**
- `$user` - Your user entity

```php
// Called when user wants to unlink their Kinde account
$this->userSync->unlinkUser($user);
```

This delegates to your `KindeUserProviderInterface::handleUserDeletion()` method.

---

## KindeTokenAuthenticator

Symfony security authenticator that handles Bearer token authentication.

**Class:** `Habityzer\KindeBundle\Security\KindeTokenAuthenticator`

This authenticator is typically configured in `security.yaml` and not used directly. However, understanding its behavior is useful:

### Behavior

1. **Token Detection:** Supports requests with `Authorization: Bearer <token>` header
2. **Token Filtering:** Skips tokens prefixed with `app_` (allows other authenticators to handle them)
3. **Test Environment:** Automatically disabled in `test` environment
4. **Email Fallback:** If token lacks email claim, fetches from Kinde UserInfo endpoint
5. **User Sync:** Automatically syncs users on every authentication

### Configuration

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            custom_authenticators:
                - Habityzer\KindeBundle\Security\KindeTokenAuthenticator
```

### Authentication Flow

```
1. Request arrives with Authorization: Bearer <token>
2. KindeTokenAuthenticator::supports() checks if it should handle
   - Returns false if no Bearer token
   - Returns false if token starts with "app_"
   - Returns false in test environment
3. KindeTokenAuthenticator::authenticate()
   - Validates token via KindeTokenValidator
   - Extracts user info from token
   - If email missing, fetches from UserInfo endpoint
   - Syncs user via KindeUserSync
   - Returns SelfValidatingPassport with user
4. On success, request continues
5. On failure, returns 401 JSON response
```

---

## KindeUserProviderInterface

Interface you must implement to connect the bundle to your user entity.

**Interface:** `Habityzer\KindeBundle\Contract\KindeUserProviderInterface`

### Methods

#### `findByKindeId(string $kindeId): ?object`

Find a user by their Kinde ID.

```php
public function findByKindeId(string $kindeId): ?object
{
    return $this->userRepository->findOneBy(['kindeId' => $kindeId]);
}
```

---

#### `syncUser(array $kindeUserData): object`

Create or update a user from Kinde data.

```php
public function syncUser(array $kindeUserData): object
{
    // Check if user exists by email (for migration scenarios)
    $user = $this->userRepository->findOneBy(['email' => $kindeUserData['email']]);
    
    if (!$user) {
        $user = new User();
        $user->setRoles(['ROLE_USER']);
    }
    
    $user->setKindeId($kindeUserData['kinde_id']);
    $user->setEmail($kindeUserData['email']);
    $user->setName($kindeUserData['name'] ?? '');
    $user->setPicture($kindeUserData['picture'] ?? null);
    
    $this->entityManager->persist($user);
    $this->entityManager->flush();
    
    return $user;
}
```

**Available `$kindeUserData` keys:**
- `kinde_id` - Kinde user ID (always present)
- `email` - User email (may be null)
- `given_name` - First name
- `family_name` - Last name
- `name` - Full name
- `picture` - Profile picture URL
- `organization_code` - Organization code

---

#### `updateUser(object $user, array $kindeUserData): void`

Update an existing user with new Kinde data.

```php
public function updateUser(object $user, array $kindeUserData): void
{
    $user->setEmail($kindeUserData['email']);
    $user->setName($kindeUserData['name'] ?? '');
    $user->setPicture($kindeUserData['picture'] ?? null);
    $user->setUpdatedAt(new \DateTimeImmutable());
    
    $this->entityManager->flush();
}
```

---

#### `handleUserDeletion(object $user): void`

Handle when a user is deleted from Kinde.

```php
public function handleUserDeletion(object $user): void
{
    // Option 1: Soft delete (recommended)
    $user->setKindeId(null);
    $user->setDeletedAt(new \DateTimeImmutable());
    
    // Option 2: Hard delete
    // $this->entityManager->remove($user);
    
    $this->entityManager->flush();
}
```

---

## Service Registration

Register your user provider with the correct tag:

```yaml
# config/services.yaml
services:
    App\Kinde\UserProvider:
        tags:
            - { name: 'habityzer_kinde.user_provider' }
```

---

## Dependency Injection

All services can be autowired in your classes:

```php
use Habityzer\KindeBundle\Service\KindeTokenValidator;
use Habityzer\KindeBundle\Service\KindeUserInfoService;
use Habityzer\KindeBundle\Service\KindeUserSync;

class MyService
{
    public function __construct(
        private readonly KindeTokenValidator $tokenValidator,
        private readonly KindeUserInfoService $userInfoService,
        private readonly KindeUserSync $userSync,
    ) {}
}
```

