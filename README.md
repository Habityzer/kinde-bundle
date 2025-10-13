# Habityzer Kinde Bundle

Symfony bundle for Kinde authentication integration with JWT validation, webhooks, and user synchronization.

## Features

- ✅ **JWT Token Validation** - Validates Kinde tokens using JWKS
- ✅ **Symfony Security Integration** - Custom authenticator for seamless integration
- ✅ **User Synchronization** - Sync users from Kinde to your database
- ✅ **Webhook Support** - Handle Kinde webhook events (user updates, subscriptions)
- ✅ **Event-Driven** - Dispatch Symfony events for business logic
- ✅ **Fully Decoupled** - Uses interfaces for app-specific logic

## Installation

```bash
composer require habityzer/kinde-bundle
```

## Configuration

```yaml
# config/packages/habityzer_kinde.yaml
habityzer_kinde:
    domain: '%env(KINDE_DOMAIN)%'
    client_id: '%env(KINDE_CLIENT_ID)%'
    client_secret: '%env(KINDE_CLIENT_SECRET)%'
    webhook_secret: '%env(KINDE_WEBHOOK_SECRET)%'
    
    # Optional
    jwks_cache_ttl: 3600  # Cache JWKS for 1 hour
```

## Usage

### 1. Implement the User Provider Interface

```php
namespace App\Kinde;

use Habityzer\KindeBundle\Contract\KindeUserProviderInterface;
use App\Entity\User;

class UserProvider implements KindeUserProviderInterface
{
    public function findByKindeId(string $kindeId): ?object
    {
        return $this->userRepository->findOneBy(['kindeId' => $kindeId]);
    }
    
    public function syncUser(array $kindeUserData): object
    {
        $user = new User();
        $user->setKindeId($kindeUserData['kinde_id']);
        $user->setEmail($kindeUserData['email']);
        $user->setName($kindeUserData['name']);
        // ... your logic
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }
    
    public function updateUser(object $user, array $kindeUserData): void
    {
        $user->setEmail($kindeUserData['email']);
        $user->setName($kindeUserData['name']);
        // ... your update logic
        
        $this->em->flush();
    }
    
    public function handleUserDeletion(object $user): void
    {
        $user->setKindeId(null); // Soft delete
        $this->em->flush();
    }
}
```

### 2. Subscribe to Webhook Events

```php
namespace App\EventSubscriber;

use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class KindeWebhookSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KindeEvents::SUBSCRIPTION_UPDATED => 'onSubscriptionUpdated',
            KindeEvents::USER_UPDATED => 'onUserUpdated',
        ];
    }
    
    public function onSubscriptionUpdated(KindeSubscriptionUpdatedEvent $event): void
    {
        // Your business logic
        $userId = $event->getUserId();
        $planName = $event->getPlanName();
        
        $this->billingService->updateUserPlan($userId, $planName);
    }
}
```

### 3. Enable Security Authenticator

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            custom_authenticators:
                - Habityzer\KindeBundle\Security\KindeTokenAuthenticator
```

## Events

The bundle dispatches the following events:

- `kinde.user.updated` - User information updated
- `kinde.user.deleted` - User deleted from Kinde
- `kinde.user.authenticated` - User authenticated
- `kinde.subscription.created` - Subscription created
- `kinde.subscription.updated` - Subscription updated
- `kinde.subscription.cancelled` - Subscription cancelled
- `kinde.subscription.reactivated` - Subscription reactivated

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.x
- Kinde account with configured application

## License

MIT

## Support

For issues and questions: https://github.com/habityzer/kinde-bundle/issues

