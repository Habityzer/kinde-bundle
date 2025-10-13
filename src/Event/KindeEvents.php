<?php

namespace Habityzer\KindeBundle\Event;

/**
 * Contains all Kinde webhook event constants
 */
final class KindeEvents
{
    /**
     * Dispatched when a user is updated in Kinde
     * @Event("Habityzer\KindeBundle\Event\KindeUserUpdatedEvent")
     */
    public const USER_UPDATED = 'kinde.user.updated';

    /**
     * Dispatched when a user is deleted in Kinde
     * @Event("Habityzer\KindeBundle\Event\KindeUserDeletedEvent")
     */
    public const USER_DELETED = 'kinde.user.deleted';

    /**
     * Dispatched when a user authenticates
     * @Event("Habityzer\KindeBundle\Event\KindeUserAuthenticatedEvent")
     */
    public const USER_AUTHENTICATED = 'kinde.user.authenticated';

    /**
     * Dispatched when a subscription is created
     * @Event("Habityzer\KindeBundle\Event\KindeSubscriptionCreatedEvent")
     */
    public const SUBSCRIPTION_CREATED = 'kinde.subscription.created';

    /**
     * Dispatched when a subscription is updated
     * @Event("Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent")
     */
    public const SUBSCRIPTION_UPDATED = 'kinde.subscription.updated';

    /**
     * Dispatched when a subscription is cancelled
     * @Event("Habityzer\KindeBundle\Event\KindeSubscriptionCancelledEvent")
     */
    public const SUBSCRIPTION_CANCELLED = 'kinde.subscription.cancelled';

    /**
     * Dispatched when a subscription is reactivated
     * @Event("Habityzer\KindeBundle\Event\KindeSubscriptionReactivatedEvent")
     */
    public const SUBSCRIPTION_REACTIVATED = 'kinde.subscription.reactivated';
}

