<?php

namespace Habityzer\KindeBundle\Service;

use Habityzer\KindeBundle\Contract\KindeUserProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Generic service to sync users from Kinde tokens
 * Uses KindeUserProviderInterface for app-specific user operations
 */
class KindeUserSync
{
    public function __construct(
        private readonly KindeUserProviderInterface $userProvider,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Sync user from Kinde token to local database
     * Creates new user or updates existing user based on Kinde ID or email
     */
    public function syncUser(array $userInfo): object
    {
        $kindeId = $userInfo['kinde_id'];
        
        if (!$kindeId) {
            throw new \RuntimeException('Missing Kinde user ID');
        }

        // Find existing user by Kinde ID
        $user = $this->userProvider->findByKindeId($kindeId);

        if (!$user) {
            $this->logger->info('Creating new user from Kinde token', [
                'email' => $userInfo['email'] ?? 'unknown',
                'kinde_id' => $kindeId
            ]);
            
            // Email is required for new users
            if (empty($userInfo['email'])) {
                throw new \RuntimeException('Email is required for new users');
            }
            
            // Delegate to app-specific provider
            $user = $this->userProvider->syncUser($userInfo);
        } else {
            $this->logger->info('Updating existing user from Kinde token', [
                'kinde_id' => $kindeId
            ]);
            
            // Update existing user
            $this->userProvider->updateUser($user, $userInfo);
        }

        $this->logger->info('User synced successfully', [
            'kinde_id' => $kindeId,
            'email' => $userInfo['email'] ?? 'unknown'
        ]);

        return $user;
    }
    
    /**
     * Find user by Kinde ID
     */
    public function findByKindeId(string $kindeId): ?object
    {
        return $this->userProvider->findByKindeId($kindeId);
    }
    
    /**
     * Unlink user from Kinde (for account deletion/unlinking)
     */
    public function unlinkUser(object $user): void
    {
        $this->userProvider->handleUserDeletion($user);
        
        $this->logger->info('User unlinked from Kinde');
    }
}

