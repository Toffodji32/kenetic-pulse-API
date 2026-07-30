<?php

namespace App\Entity;

use App\Repository\WithdrawalRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WithdrawalRequestRepository::class)]
class WithdrawalRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    public const OPERATOR_MTN = 'mtn';
    public const OPERATOR_MOOV = 'moov';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gym $gym = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 20)]
    private ?string $mobileMoneyNumber = null;

    #[ORM\Column(length: 10)]
    private ?string $mobileMoneyOperator = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private ?\DateTime $requestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $processedAt = null;

    #[ORM\ManyToOne]
    private ?User $processedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fedapayTransferId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNotes = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGym(): ?Gym
    {
        return $this->gym;
    }

    public function setGym(?Gym $gym): static
    {
        $this->gym = $gym;
        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getMobileMoneyNumber(): ?string
    {
        return $this->mobileMoneyNumber;
    }

    public function setMobileMoneyNumber(string $mobileMoneyNumber): static
    {
        $this->mobileMoneyNumber = $mobileMoneyNumber;
        return $this;
    }

    public function getMobileMoneyOperator(): ?string
    {
        return $this->mobileMoneyOperator;
    }

    public function setMobileMoneyOperator(string $mobileMoneyOperator): static
    {
        $this->mobileMoneyOperator = $mobileMoneyOperator;
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

    public function getRequestedAt(): ?\DateTime
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTime $requestedAt): static
    {
        $this->requestedAt = $requestedAt;
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

    public function getProcessedBy(): ?User
    {
        return $this->processedBy;
    }

    public function setProcessedBy(?User $processedBy): static
    {
        $this->processedBy = $processedBy;
        return $this;
    }

    public function getFedapayTransferId(): ?string
    {
        return $this->fedapayTransferId;
    }

    public function setFedapayTransferId(?string $fedapayTransferId): static
    {
        $this->fedapayTransferId = $fedapayTransferId;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): static
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }
}
