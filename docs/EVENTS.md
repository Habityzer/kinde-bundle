# Events Reference

This document provides complete documentation for all events dispatched by the Habityzer Kinde Bundle.

## Overview

The bundle uses Symfony's event dispatcher to notify your application when Kinde webhook events occur. This allows you to implement custom business logic without modifying bundle code.

## Event Constants

All event names are defined in `Habityzer\KindeBundle\Event\KindeEvents`:

```php
use Habityzer\KindeBundle\Event\KindeEvents;

// User events
KindeEvents::USER_UPDATED        // 'kinde.user.updated'
KindeEvents::USER_DELETED        // 'kinde.user.deleted'
KindeEvents::USER_AUTHENTICATED  // 'kinde.user.authenticated'

// Subscription events
KindeEvents::SUBSCRIPTION_CREATED     // 'kinde.subscription.created'
KindeEvents::SUBSCRIPTION_UPDATED     // 'kinde.subscription.updated'
KindeEvents::SUBSCRIPTION_CANCELLED   // 'kinde.subscription.cancelled'
KindeEvents::SUBSCRIPTION_REACTIVATED // 'kinde.subscription.reactivated'
```

---

## User Events

### KindeUserUpdatedEvent

Dispatched when a user's information is updated in Kinde.

**Event Name:** `kinde.user.updated`

**Class:** `Habityzer\KindeBundle\Event\KindeUserUpdatedEvent`

#### Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getData()` | `array` | Returns the complete webhook payload data |
| `getKindeId()` | `string` | Returns the Kinde user ID |
| `getEmail()` | `?string` | Returns the user's email (may be null) |

#### Example Usage

```php
use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeUserUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KindeEvents::USER_UPDATED => 'onUserUpdated',
        ];
    }

    public function onUserUpdated(KindeUserUpdatedEvent $event): void
    {
        $kindeId = $event->getKindeId();
        $email = $event->getEmail();
        $rawData = $event->getData();
        
        // Update user in your database
        $user = $this->userRepository->findOneBy(['kindeId' => $kindeId]);
        if ($user && $email) {
            $user->setEmail($email);
            $this->entityManager->flush();
        }
    }
}
```

#### Example Payload

```php
$event->getData();
// Returns:
[
    'id' => 'kp_abc123...',
    'email' => 'user@example.com',
    'first_name' => 'John',
    'last_name' => 'Doe',
    // ... additional Kinde user fields
]
```

---

### KindeUserDeletedEvent

Dispatched when a user is deleted from Kinde.

**Event Name:** `kinde.user.deleted`

**Class:** `Habityzer\KindeBundle\Event\KindeUserDeletedEvent`

#### Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getData()` | `array` | Returns the complete webhook payload data |
| `getKindeId()` | `string` | Returns the deleted user's Kinde ID |

#### Example Usage

```php
use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeUserDeletedEvent;

public function onUserDeleted(KindeUserDeletedEvent $event): void
{
    $kindeId = $event->getKindeId();
    
    // Handle user deletion (soft delete recommended)
    $user = $this->userRepository->findOneBy(['kindeId' => $kindeId]);
    if ($user) {
        $user->setKindeId(null);
        $user->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        // Optionally notify other services
        $this->notificationService->userDeleted($user);
    }
}
```

---

### KindeUserAuthenticatedEvent

Dispatched when Kinde sends an authentication webhook (if configured).

**Event Name:** `kinde.user.authenticated`

**Class:** `Habityzer\KindeBundle\Event\KindeUserAuthenticatedEvent`

#### Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getData()` | `array` | Returns the complete webhook payload data |
| `getKindeId()` | `string` | Returns the authenticated user's Kinde ID |
| `getTimestamp()` | `?string` | Returns the authentication timestamp |

#### Example Usage

```php
use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeUserAuthenticatedEvent;

public function onUserAuthenticated(KindeUserAuthenticatedEvent $event): void
{
    $kindeId = $event->getKindeId();
    $timestamp = $event->getTimestamp();
    
    // Log authentication for analytics
    $this->analyticsService->recordLogin($kindeId, $timestamp);
    
    // Update last login timestamp
    $user = $this->userRepository->findOneBy(['kindeId' => $kindeId]);
    if ($user) {
        $user->setLastLoginAt(new \DateTimeImmutable($timestamp));
        $this->entityManager->flush();
    }
}
```

---

## Subscription Events

All subscription events share common methods for accessing user and subscription information.

### Common Subscription Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getData()` | `array` | Returns the complete webhook payload data |
| `getUserId()` | `?string` | Returns the subscriber's user ID |
| `getSubscriptionId()` | `?string` | Returns the subscription ID |
| `getPlanName()` | `string` | Returns the plan name (defaults to 'unknown' or 'pro') |

---

### KindeSubscriptionCreatedEvent

Dispatched when a new subscription is created.

**Event Name:** `kinde.subscription.created`

**Class:** `Habityzer\KindeBundle\Event\KindeSubscriptionCreatedEvent`

#### Example Usage

```php
use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeSubscriptionCreatedEvent;

public function onSubscriptionCreated(KindeSubscriptionCreatedEvent $event): void
{
    $userId = $event->getUserId();
    $subscriptionId = $event->getSubscriptionId();
    $planName = $event->getPlanName();
    
    // Upgrade user to premium features
    $user = $this->userRepository->findOneBy(['kindeId' => $userId]);
    if ($user) {
        $user->setPlan($planName);
        $user->setSubscriptionId($subscriptionId);
        $user->setSubscribedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        // Send welcome email
        $this->mailer->sendSubscriptionWelcome($user, $planName);
    }
}
```

---

### KindeSubscriptionUpdatedEvent

Dispatched when a subscription is updated (e.g., plan change).

**Event Name:** `kinde.subscription.updated`

**Class:** `Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent`

#### Example Usage

```php
use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent;

public function onSubscriptionUpdated(KindeSubscriptionUpdatedEvent $event): void
{
    $userId = $event->getUserId();
    $newPlan = $event->getPlanName();
    $rawData = $event->getData();
    
    $user = $this->userRepository->findOneBy(['kindeId' => $userId]);
    if ($user) {
        $oldPlan = $user->getPlan();
        $user->setPlan($newPlan);
        $this->entityManager->flush();
        
        // Log plan change
        $this->logger->info('Subscription updated', [
            'user_id' => $userId,
            'old_plan' => $oldPlan,
            'new_plan' => $newPlan,
        ]);
        
        // Adjust features based on plan
        $this->featureService->syncUserFeatures($user);
    }
}
```

---

### KindeSubscriptionCancelledEvent

Dispatched when a subscription is cancelled.

**Event Name:** `kinde.subscription.cancelled`

**Class:** `Habityzer\KindeBundle\Event\KindeSubscriptionCancelledEvent`

#### Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getData()` | `array` | Returns the complete webhook payload data |
| `getUserId()` | `?string` | Returns the subscriber's user ID |
| `getSubscriptionId()` | `?string` | Returns the cancelled subscription ID |

> **Note:** This event does not include `getPlanName()` since the subscription is being cancelled.

#### Example Usage

```php
use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeSubscriptionCancelledEvent;

public function onSubscriptionCancelled(KindeSubscriptionCancelledEvent $event): void
{
    $userId = $event->getUserId();
    $subscriptionId = $event->getSubscriptionId();
    
    $user = $this->userRepository->findOneBy(['kindeId' => $userId]);
    if ($user) {
        $user->setPlan('free');
        $user->setSubscriptionId(null);
        $user->setCancelledAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        // Send retention email
        $this->mailer->sendCancellationFeedback($user);
        
        // Revoke premium features (consider grace period)
        $this->featureService->scheduleDowngrade($user, '+7 days');
    }
}
```

---

### KindeSubscriptionReactivatedEvent

Dispatched when a cancelled subscription is reactivated.

**Event Name:** `kinde.subscription.reactivated`

**Class:** `Habityzer\KindeBundle\Event\KindeSubscriptionReactivatedEvent`

#### Example Usage

```php
use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeSubscriptionReactivatedEvent;

public function onSubscriptionReactivated(KindeSubscriptionReactivatedEvent $event): void
{
    $userId = $event->getUserId();
    $planName = $event->getPlanName();
    
    $user = $this->userRepository->findOneBy(['kindeId' => $userId]);
    if ($user) {
        $user->setPlan($planName);
        $user->setCancelledAt(null);
        $user->setReactivatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        // Cancel any pending downgrades
        $this->featureService->cancelScheduledDowngrade($user);
        
        // Send welcome back email
        $this->mailer->sendReactivationWelcome($user);
    }
}
```

---

## Complete Event Subscriber Example

Here's a complete example handling all events:

```php
<?php

namespace App\EventSubscriber;

use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeUserUpdatedEvent;
use Habityzer\KindeBundle\Event\KindeUserDeletedEvent;
use Habityzer\KindeBundle\Event\KindeUserAuthenticatedEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionCreatedEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionCancelledEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionReactivatedEvent;
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
            // User events
            KindeEvents::USER_UPDATED => 'onUserUpdated',
            KindeEvents::USER_DELETED => 'onUserDeleted',
            KindeEvents::USER_AUTHENTICATED => 'onUserAuthenticated',
            
            // Subscription events
            KindeEvents::SUBSCRIPTION_CREATED => 'onSubscriptionCreated',
            KindeEvents::SUBSCRIPTION_UPDATED => 'onSubscriptionUpdated',
            KindeEvents::SUBSCRIPTION_CANCELLED => 'onSubscriptionCancelled',
            KindeEvents::SUBSCRIPTION_REACTIVATED => 'onSubscriptionReactivated',
        ];
    }

    public function onUserUpdated(KindeUserUpdatedEvent $event): void
    {
        $this->logger->info('Kinde: User updated', [
            'kinde_id' => $event->getKindeId(),
            'email' => $event->getEmail(),
        ]);
    }

    public function onUserDeleted(KindeUserDeletedEvent $event): void
    {
        $this->logger->info('Kinde: User deleted', [
            'kinde_id' => $event->getKindeId(),
        ]);
    }

    public function onUserAuthenticated(KindeUserAuthenticatedEvent $event): void
    {
        $this->logger->info('Kinde: User authenticated', [
            'kinde_id' => $event->getKindeId(),
            'timestamp' => $event->getTimestamp(),
        ]);
    }

    public function onSubscriptionCreated(KindeSubscriptionCreatedEvent $event): void
    {
        $this->logger->info('Kinde: Subscription created', [
            'user_id' => $event->getUserId(),
            'subscription_id' => $event->getSubscriptionId(),
            'plan' => $event->getPlanName(),
        ]);
    }

    public function onSubscriptionUpdated(KindeSubscriptionUpdatedEvent $event): void
    {
        $this->logger->info('Kinde: Subscription updated', [
            'user_id' => $event->getUserId(),
            'subscription_id' => $event->getSubscriptionId(),
            'plan' => $event->getPlanName(),
        ]);
    }

    public function onSubscriptionCancelled(KindeSubscriptionCancelledEvent $event): void
    {
        $this->logger->info('Kinde: Subscription cancelled', [
            'user_id' => $event->getUserId(),
            'subscription_id' => $event->getSubscriptionId(),
        ]);
    }

    public function onSubscriptionReactivated(KindeSubscriptionReactivatedEvent $event): void
    {
        $this->logger->info('Kinde: Subscription reactivated', [
            'user_id' => $event->getUserId(),
            'subscription_id' => $event->getSubscriptionId(),
            'plan' => $event->getPlanName(),
        ]);
    }
}
```

---

## Accessing Raw Webhook Data

All events provide access to the complete webhook payload via `getData()`. This is useful when Kinde adds new fields that aren't yet mapped to specific methods:

```php
public function onSubscriptionUpdated(KindeSubscriptionUpdatedEvent $event): void
{
    $rawData = $event->getData();
    
    // Access any field from the webhook payload
    $customField = $rawData['custom_field'] ?? null;
    $metadata = $rawData['metadata'] ?? [];
}
```

---

## Event Priority

If you need to control the order of event listeners, use priority:

```php
public static function getSubscribedEvents(): array
{
    return [
        KindeEvents::USER_UPDATED => ['onUserUpdated', 10], // Higher priority
        KindeEvents::USER_DELETED => ['onUserDeleted', 0],  // Normal priority
    ];
}
```

Higher numbers execute first.

