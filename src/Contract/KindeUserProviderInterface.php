<?php

namespace Habityzer\KindeBundle\Contract;

/**
 * Interface for providing user lookup and sync operations
 * Implement this in your application to handle user management
 */
interface KindeUserProviderInterface
{
    /**
     * Find user by Kinde ID
     */
    public function findByKindeId(string $kindeId): ?object;

    /**
     * Sync user from Kinde data
     * Create new user or update existing user
     */
    public function syncUser(array $kindeUserData): object;

    /**
     * Update user information from Kinde data
     */
    public function updateUser(object $user, array $kindeUserData): void;

    /**
     * Handle user deletion from Kinde
     */
    public function handleUserDeletion(object $user): void;
}

