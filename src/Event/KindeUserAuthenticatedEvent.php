<?php

namespace Habityzer\KindeBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class KindeUserAuthenticatedEvent extends Event
{
    public function __construct(
        private readonly array $data
    ) {}

    public function getData(): array
    {
        return $this->data;
    }

    public function getKindeId(): string
    {
        return $this->data['id'] ?? 'unknown';
    }

    public function getTimestamp(): ?string
    {
        return $this->data['timestamp'] ?? null;
    }
}

