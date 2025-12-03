# Installation Guide

## Step 1: Install the Bundle

### Option A: From Packagist (when published)
```bash
composer require habityzer/kinde-bundle
```

### Option B: From Local Path (development)
Add to your `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../kinde-bundle"
        }
    ],
    "require": {
        "habityzer/kinde-bundle": "@dev"
    }
}
```

Then run:
```bash
composer require habityzer/kinde-bundle:@dev
```

---

## Step 2: Configure the Bundle

Create `config/packages/habityzer_kinde.yaml`:

```yaml
habityzer_kinde:
    domain: '%env(KINDE_DOMAIN)%'
    client_id: '%env(KINDE_CLIENT_ID)%'
    client_secret: '%env(KINDE_CLIENT_SECRET)%'  # Optional
    webhook_secret: '%env(KINDE_WEBHOOK_SECRET)%'
    jwks_cache_ttl: 3600  # Optional: cache time in seconds
```

Add to your `.env`:
```env
KINDE_DOMAIN=https://your-business.kinde.com
KINDE_CLIENT_ID=your_client_id
KINDE_CLIENT_SECRET=your_client_secret
KINDE_WEBHOOK_SECRET=your_webhook_secret
```

---

## Step 3: Implement User Provider Interface

Create `src/Kinde/UserProvider.php`:

```php
<?php

namespace App\Kinde;

use Habityzer\KindeBundle\Contract\KindeUserProviderInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserProvider implements KindeUserProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function findByKindeId(string $kindeId): ?object
    {
        return $this->userRepository->findOneBy(['kindeId' => $kindeId]);
    }

    public function syncUser(array $kindeUserData): object
    {
        // Find by email first (for migration scenarios)
        $user = $this->userRepository->findOneBy(['email' => $kindeUserData['email']]);
        
        if (!$user) {
            $user = new User();
            $user->setRoles(['ROLE_USER']);
        }
        
        // Update user data
        $user->setKindeId($kindeUserData['kinde_id']);
        $user->setEmail($kindeUserData['email']);
        $user->setName($kindeUserData['name'] ?? '');
        $user->setPicture($kindeUserData['picture'] ?? null);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user;
    }

    public function updateUser(object $user, array $kindeUserData): void
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Expected User entity');
        }
        
        $user->setEmail($kindeUserData['email']);
        $user->setName($kindeUserData['name'] ?? '');
        $user->setPicture($kindeUserData['picture'] ?? null);
        $user->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
    }

    public function handleUserDeletion(object $user): void
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Expected User entity');
        }
        
        // Soft delete: unlink from Kinde
        $user->setKindeId(null);
        $user->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
    }
}
```

Register it as a service in `config/services.yaml`:
```yaml
services:
    App\Kinde\UserProvider:
        tags:
            - { name: 'habityzer_kinde.user_provider' }
```

---

## Step 4: Create Event Subscribers (Optional but Recommended)

Create `src/EventSubscriber/KindeWebhookSubscriber.php`:

```php
<?php

namespace App\EventSubscriber;

use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionCancelledEvent;
use Habityzer\KindeBundle\Event\KindeUserDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class KindeWebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KindeEvents::SUBSCRIPTION_UPDATED => 'onSubscriptionUpdated',
            KindeEvents::SUBSCRIPTION_CANCELLED => 'onSubscriptionCancelled',
            KindeEvents::USER_DELETED => 'onUserDeleted',
        ];
    }

    public function onSubscriptionUpdated(KindeSubscriptionUpdatedEvent $event): void
    {
        $userId = $event->getUserId();
        $planName = $event->getPlanName();
        
        $this->logger->info('Subscription updated', [
            'user_id' => $userId,
            'plan' => $planName
        ]);
        
        // YOUR BUSINESS LOGIC HERE
        // e.g., $this->billingService->updateUserPlan($userId, $planName);
    }

    public function onSubscriptionCancelled(KindeSubscriptionCancelledEvent $event): void
    {
        $userId = $event->getUserId();
        
        $this->logger->info('Subscription cancelled', [
            'user_id' => $userId
        ]);
        
        // YOUR BUSINESS LOGIC HERE
        // e.g., $this->billingService->downgradeToFree($userId);
    }

    public function onUserDeleted(KindeUserDeletedEvent $event): void
    {
        $kindeId = $event->getKindeId();
        
        $this->logger->info('User deleted from Kinde', [
            'kinde_id' => $kindeId
        ]);
        
        // YOUR BUSINESS LOGIC HERE
    }
}
```

---

## Step 5: Configure Security

Update `config/packages/security.yaml`:

```yaml
security:
    firewalls:
        # Allow public access to Kinde webhook
        kinde_webhook:
            pattern: ^/api/webhooks/kinde$
            stateless: true
            security: false
        
        # Main API firewall
        api:
            pattern: ^/api/
            stateless: true
            custom_authenticators:
                - Habityzer\KindeBundle\Security\KindeTokenAuthenticator
                # You can add other authenticators here (e.g., AppTokenAuthenticator)
```

---

## Step 6: Ensure Your User Entity Has kindeId

```php
#[ORM\Entity]
class User implements UserInterface
{
    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $kindeId = null;

    public function getKindeId(): ?string
    {
        return $this->kindeId;
    }

    public function setKindeId(?string $kindeId): self
    {
        $this->kindeId = $kindeId;
        return $this;
    }
    
    // ... other methods
}
```

Create migration:
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

---

## Step 7: Test the Integration

### Test JWT Authentication:
```bash
# Get a token from your Nuxt app, then:
php bin/console kinde:debug-token YOUR_JWT_TOKEN
```

### Test API Request:

**Important:** Prefix your token with `kinde_`:

```bash
curl -H "Authorization: Bearer kinde_YOUR_JWT_TOKEN" \
     https://your-api.com/api/your-endpoint
```

The `kinde_` prefix allows the authenticator to identify Kinde tokens and coexist with other authentication methods.

### Test Webhook:
Configure webhook URL in Kinde dashboard:
```
https://your-api.com/api/webhooks/kinde
```

---

## Troubleshooting

### Issue: Configuration error during installation
**Error:** `The child config "domain" under "habityzer_kinde" must be configured`

**Solution:** This happens when the bundle is installed but environment variables aren't set yet. To fix:

1. Add the required environment variables to your `.env` file:
```env
KINDE_DOMAIN=your-business.kinde.com
KINDE_CLIENT_ID=your-client-id
KINDE_CLIENT_SECRET=your-client-secret
KINDE_WEBHOOK_SECRET=your-webhook-secret
```

2. Make sure the configuration file exists at `config/packages/habityzer_kinde.yaml`. If not, create it with:
```yaml
habityzer_kinde:
    domain: '%env(KINDE_DOMAIN)%'
    client_id: '%env(KINDE_CLIENT_ID)%'
    client_secret: '%env(KINDE_CLIENT_SECRET)%'
    webhook_secret: '%env(KINDE_WEBHOOK_SECRET)%'
    jwks_cache_ttl: 3600
    enable_webhook_route: true
```

3. Clear the cache:
```bash
php bin/console cache:clear
```

### Issue: "User provider not found"
**Solution:** Make sure you've registered your `UserProvider` service with the correct tag.

### Issue: "Invalid JWT token"
**Solution:** 
1. Check that `KINDE_DOMAIN` and `KINDE_CLIENT_ID` match your Kinde app
2. Ensure your frontend is requesting the correct scopes: `openid profile email`
3. Use `kinde:debug-token` command to inspect the token

### Issue: "Webhook signature verification failed"
**Solution:** 
1. Check that `KINDE_WEBHOOK_SECRET` matches the secret in Kinde dashboard
2. Ensure webhook is configured correctly in Kinde

---

## Next Steps

- Review [README.md](README.md) for detailed usage
- Check [PROGRESS.md](PROGRESS.md) for implementation status
- Subscribe to more webhook events as needed
- Customize user provider logic for your use case

---

## Support

For issues: https://github.com/habityzer/kinde-bundle/issues

