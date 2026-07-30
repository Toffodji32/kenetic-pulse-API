<?php

namespace App\Entity;

use App\Repository\GymWalletRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GymWalletRepository::class)]
class GymWallet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'gymWallet')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gym $gym = null;

    #[ORM\Column]
    private int $balanceAvailable = 0;

    #[ORM\Column]
    private int $balancePending = 0;

    #[ORM\Column]
    private int $balancePendingWithdrawal = 0;

    #[ORM\Column]
    private int $totalEarned = 0;

    #[ORM\Column(length: 10)]
    private string $currency = 'XOF';

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

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

    public function getBalanceAvailable(): int
    {
        return $this->balanceAvailable;
    }

    public function setBalanceAvailable(int $balanceAvailable): static
    {
        $this->balanceAvailable = $balanceAvailable;
        return $this;
    }

    public function getBalancePending(): int
    {
        return $this->balancePending;
    }

    public function setBalancePending(int $balancePending): static
    {
        $this->balancePending = $balancePending;
        return $this;
    }

    public function getBalancePendingWithdrawal(): int
    {
        return $this->balancePendingWithdrawal;
    }

    public function setBalancePendingWithdrawal(int $balancePendingWithdrawal): static
    {
        $this->balancePendingWithdrawal = $balancePendingWithdrawal;
        return $this;
    }

    public function getTotalEarned(): int
    {
        return $this->totalEarned;
    }

    public function setTotalEarned(int $totalEarned): static
    {
        $this->totalEarned = $totalEarned;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
