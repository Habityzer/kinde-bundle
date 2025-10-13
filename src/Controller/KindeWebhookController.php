<?php

namespace Habityzer\KindeBundle\Controller;

use Habityzer\KindeBundle\Event\KindeEvents;
use Habityzer\KindeBundle\Event\KindeUserUpdatedEvent;
use Habityzer\KindeBundle\Event\KindeUserDeletedEvent;
use Habityzer\KindeBundle\Event\KindeUserAuthenticatedEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionCreatedEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionUpdatedEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionCancelledEvent;
use Habityzer\KindeBundle\Event\KindeSubscriptionReactivatedEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles Kinde webhook events
 * Verifies signatures and dispatches events for business logic
 */
class KindeWebhookController extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly string $kindeWebhookSecret
    ) {}

    #[Route('/api/webhooks/kinde', name: 'kinde_webhook', methods: ['POST'])]
    public function kindeWebhook(Request $request): JsonResponse
    {
        try {
            $body = $request->getContent();
            $signature = $request->headers->get('kinde-signature');
            
            // Verify webhook signature for security
            if (!$this->verifyWebhookSignature($body, $signature)) {
                $this->logger->warning('Invalid Kinde webhook signature', [
                    'signature' => $signature,
                    'headers' => $request->headers->all()
                ]);
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
            }
            
            $payload = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON payload');
            }
            
            $this->logger->info('Received Kinde webhook', [
                'type' => $payload['type'] ?? 'unknown',
                'source' => $payload['source'] ?? 'unknown'
            ]);
            
            // Dispatch appropriate event based on webhook type
            $this->dispatchWebhookEvent($payload);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Webhook processed',
                'timestamp' => (new \DateTimeImmutable())->format('c')
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Kinde webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent(),
                'headers' => $request->headers->all()
            ]);
            
            return new JsonResponse([
                'error' => 'Webhook processing failed',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify webhook signature using HMAC SHA256
     */
    private function verifyWebhookSignature(string $body, ?string $signature): bool
    {
        if (!$signature || empty($this->kindeWebhookSecret)) {
            return false;
        }
        
        // Remove the signature prefix if present (e.g., "sha256=")
        $signature = str_replace('sha256=', '', $signature);
        
        $expectedSignature = hash_hmac('sha256', $body, $this->kindeWebhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Dispatch appropriate event based on webhook type
     */
    private function dispatchWebhookEvent(array $payload): void
    {
        $data = $payload['data'] ?? [];
        
        match($payload['type']) {
            'user.updated' => $this->eventDispatcher->dispatch(
                new KindeUserUpdatedEvent($data),
                KindeEvents::USER_UPDATED
            ),
            
            'user.deleted' => $this->eventDispatcher->dispatch(
                new KindeUserDeletedEvent($data),
                KindeEvents::USER_DELETED
            ),
            
            'user.authenticated' => $this->eventDispatcher->dispatch(
                new KindeUserAuthenticatedEvent($data),
                KindeEvents::USER_AUTHENTICATED
            ),
            
            'subscription.created' => $this->eventDispatcher->dispatch(
                new KindeSubscriptionCreatedEvent($data),
                KindeEvents::SUBSCRIPTION_CREATED
            ),
            
            'subscription.updated' => $this->eventDispatcher->dispatch(
                new KindeSubscriptionUpdatedEvent($data),
                KindeEvents::SUBSCRIPTION_UPDATED
            ),
            
            'subscription.cancelled' => $this->eventDispatcher->dispatch(
                new KindeSubscriptionCancelledEvent($data),
                KindeEvents::SUBSCRIPTION_CANCELLED
            ),
            
            'subscription.reactivated' => $this->eventDispatcher->dispatch(
                new KindeSubscriptionReactivatedEvent($data),
                KindeEvents::SUBSCRIPTION_REACTIVATED
            ),
            
            default => $this->logger->warning('Unknown Kinde webhook event received', [
                'type' => $payload['type'] ?? 'unknown',
                'payload' => $payload
            ])
        };
    }
}

