<?php

namespace App\Entity;

use App\Repository\WebhookEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookEventRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_WEBHOOK_EVENT_ID', fields: ['fedapayEventId'])]
class WebhookEvent
{
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IGNORED = 'ignored';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $fedapayEventId = null;

    #[ORM\Column(length: 50)]
    private ?string $eventType = null;

    #[ORM\Column(type: Types::JSON)]
    private ?array $payload = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $processedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFedapayEventId(): ?string
    {
        return $this->fedapayEventId;
    }

    public function setFedapayEventId(string $fedapayEventId): static
    {
        $this->fedapayEventId = $fedapayEventId;
        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getProcessedAt(): ?\DateTime
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTime $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }
}
