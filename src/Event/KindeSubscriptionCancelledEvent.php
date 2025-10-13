<?php

namespace Habityzer\KindeBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class KindeSubscriptionCancelledEvent extends Event
{
    public function __construct(
        private readonly array $data
    ) {}

    public function getData(): array
    {
        return $this->data;
    }

    public function getUserId(): ?string
    {
        return $this->data['user_id'] ?? $this->data['subscriber_id'] ?? null;
    }

    public function getSubscriptionId(): ?string
    {
        return $this->data['id'] ?? null;
    }
}

