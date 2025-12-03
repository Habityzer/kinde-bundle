# Habityzer Kinde Bundle

Symfony bundle for Kinde authentication integration with JWT validation, webhooks, and user synchronization.

## Features

- ✅ **JWT Token Validation** - Validates Kinde tokens using JWKS with automatic caching
- ✅ **Symfony Security Integration** - Custom authenticator for seamless integration
- ✅ **User Synchronization** - Sync users from Kinde to your database
- ✅ **Webhook Support** - Handle Kinde webhook events (user updates, subscriptions)
- ✅ **Event-Driven** - Dispatch Symfony events for business logic
- ✅ **Fully Decoupled** - Uses interfaces for app-specific logic
- ✅ **Debug Command** - CLI tool to inspect and debug JWT tokens

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.x
- Kinde account with configured application

## Installation

```bash
composer require habityzer/kinde-bundle
```

The bundle installs successfully without configuration, but you **must** configure it before using:

### 1. Set Environment Variables

Add to your `.env` file:

```env
KINDE_DOMAIN=https://your-business.kinde.com
KINDE_CLIENT_ID=your-client-id-from-kinde
KINDE_CLIENT_SECRET=your-client-secret
KINDE_WEBHOOK_SECRET=your-webhook-secret
```

Get these values from your [Kinde Dashboard](https://app.kinde.com/settings/applications). See [Kinde Setup Guide](docs/KINDE_SETUP.md) for detailed instructions.

### 2. Create Configuration File

Create `config/packages/habityzer_kinde.yaml`:

```yaml
habityzer_kinde:
    domain: '%env(KINDE_DOMAIN)%'
    client_id: '%env(KINDE_CLIENT_ID)%'
    client_secret: '%env(KINDE_CLIENT_SECRET)%'
    webhook_secret: '%env(KINDE_WEBHOOK_SECRET)%'
```

### 3. Clear Cache

```bash
php bin/console cache:clear
```

> **Note:** The bundle will throw helpful runtime errors if you try to use authentication without proper configuration.

## Configuration Reference

```yaml
# config/packages/habityzer_kinde.yaml
habityzer_kinde:
    # Required: Your Kinde domain (e.g., https://your-business.kinde.com)
    domain: '%env(KINDE_DOMAIN)%'
    
    # Required: Kinde application client ID
    client_id: '%env(KINDE_CLIENT_ID)%'
    
    # Optional: Kinde application client secret (for server-side flows)
    client_secret: '%env(KINDE_CLIENT_SECRET)%'
    
    # Required for webhooks: Secret for webhook signature verification
    webhook_secret: '%env(KINDE_WEBHOOK_SECRET)%'
    
    # Optional: JWKS cache duration in seconds (default: 3600 = 1 hour)
    jwks_cache_ttl: 3600
    
    # Optional: Auto-register webhook route at /api/webhooks/kinde (default: true)
    enable_webhook_route: true
```

## Quick Start

### 1. Implement the User Provider Interface

Create a class that implements `KindeUserProviderInterface` to handle user management:

```php
namespace App\Kinde;

use Habityzer\KindeBundle\Contract\KindeUserProviderInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserProvider implements KindeUserProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em
    ) {}

    public function findByKindeId(string $kindeId): ?object
    {
        return $this->userRepository->findOneBy(['kindeId' => $kindeId]);
    }
    
    public function syncUser(array $kindeUserData): object
    {
        $user = new User();
        $user->setKindeId($kindeUserData['kinde_id']);
        $user->setEmail($kindeUserData['email']);
        $user->setName($kindeUserData['name'] ?? '');
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }
    
    public function updateUser(object $user, array $kindeUserData): void
    {
        $user->setEmail($kindeUserData['email']);
        $user->setName($kindeUserData['name'] ?? '');
        $this->em->flush();
    }
    
    public function handleUserDeletion(object $user): void
    {
        $user->setKindeId(null); // Soft delete approach
        $this->em->flush();
    }
}
```

Register it as a service:

```yaml
# config/services.yaml
services:
    App\Kinde\UserProvider:
        tags:
            - { name: 'habityzer_kinde.user_provider' }
```

### 2. Configure Security

```yaml
# config/packages/security.yaml
security:
    firewalls:
        # Allow public access to Kinde webhook
        kinde_webhook:
            pattern: ^/api/webhooks/kinde$
            stateless: true
            security: false
        
        # API firewall with Kinde authentication
        api:
            pattern: ^/api/
            stateless: true
            custom_authenticators:
                - Habityzer\KindeBundle\Security\KindeTokenAuthenticator
```

### 3. Subscribe to Webhook Events

```php
namespace App\EventSubscriber;

use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent;
use Habityzer\KindeBundle\Event\KindeUserDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class KindeWebhookSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KindeEvents::SUBSCRIPTION_UPDATED => 'onSubscriptionUpdated',
            KindeEvents::USER_DELETED => 'onUserDeleted',
        ];
    }
    
    public function onSubscriptionUpdated(KindeSubscriptionUpdatedEvent $event): void
    {
        $userId = $event->getUserId();
        $planName = $event->getPlanName();
        // Your business logic here
    }
    
    public function onUserDeleted(KindeUserDeletedEvent $event): void
    {
        $kindeId = $event->getKindeId();
        // Your cleanup logic here
    }
}
```

## Events

The bundle dispatches the following Symfony events:

| Event Constant | Event Name | Description |
|----------------|------------|-------------|
| `KindeEvents::USER_UPDATED` | `kinde.user.updated` | User information updated in Kinde |
| `KindeEvents::USER_DELETED` | `kinde.user.deleted` | User deleted from Kinde |
| `KindeEvents::USER_AUTHENTICATED` | `kinde.user.authenticated` | User authenticated via webhook |
| `KindeEvents::SUBSCRIPTION_CREATED` | `kinde.subscription.created` | New subscription created |
| `KindeEvents::SUBSCRIPTION_UPDATED` | `kinde.subscription.updated` | Subscription plan changed |
| `KindeEvents::SUBSCRIPTION_CANCELLED` | `kinde.subscription.cancelled` | Subscription cancelled |
| `KindeEvents::SUBSCRIPTION_REACTIVATED` | `kinde.subscription.reactivated` | Subscription reactivated |

📖 See [Events Reference](docs/EVENTS.md) for complete event documentation with all available methods.

## Debug Command

Debug JWT tokens to inspect claims and troubleshoot issues:

```bash
php bin/console kinde:debug-token YOUR_JWT_TOKEN
```

Output includes:
- Token header (algorithm, type)
- All payload claims
- Email presence check with fix suggestions
- Token expiration status

## Documentation

| Document | Description |
|----------|-------------|
| [Installation Guide](INSTALL.md) | Detailed step-by-step installation |
| [Events Reference](docs/EVENTS.md) | Complete event classes documentation |
| [Services API](docs/SERVICES.md) | Services and their public methods |
| [Kinde Setup](docs/KINDE_SETUP.md) | Configure Kinde dashboard for this bundle |
| [Advanced Usage](docs/ADVANCED.md) | Advanced scenarios and customization |

## Architecture

```
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────┐
│  HTTP Request   │────▶│ KindeTokenAuthenticator │────▶│  Your User     │
│  (Bearer Token) │     └──────────────────────┘     │   Entity        │
└─────────────────┘              │                   └─────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    ▼                         ▼
          ┌─────────────────┐      ┌─────────────────┐
          │ KindeTokenValidator │      │  KindeUserSync   │
          │  (JWKS validation)  │      │ (User provider)  │
          └─────────────────┘      └─────────────────┘
                    │                         │
                    ▼                         ▼
          ┌─────────────────┐      ┌─────────────────────┐
          │ KindeUserInfoService │      │ KindeUserProviderInterface │
          │ (Fallback for email) │      │   (Your implementation)    │
          └─────────────────┘      └─────────────────────┘
```

## License

MIT

## Support

For issues and questions: https://github.com/habityzer/kinde-bundle/issues
