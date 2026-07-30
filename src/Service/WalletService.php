<?php

namespace App\Service;

use App\Entity\Gym;
use App\Entity\GymWallet;
use App\Entity\WalletTransaction;
use App\Entity\WithdrawalRequest;
use App\Exception\InsufficientBalanceException;
use Doctrine\ORM\EntityManagerInterface;

class WalletService
{
    public function __construct(
        private EntityManagerInterface $em,
        private int $commissionRate = 5,
    ) {}

    public function getOrCreateWallet(Gym $gym): GymWallet
    {
        $wallet = $gym->getGymWallet();
        if (!$wallet) {
            $wallet = new GymWallet();
            $wallet->setGym($gym);
            $this->em->persist($wallet);
            $gym->setGymWallet($wallet);
        }
        return $wallet;
    }

    public function credit(Gym $gym, int $amount, string $reference, string $description, array $metadata = [], ?int $commissionOverride = null): GymWallet
    {
        return $this->em->wrapInTransaction(fn() => $this->doCredit($gym, $amount, $reference, $description, $metadata, $commissionOverride));
    }

    public function debit(Gym $gym, int $amount, string $reference, string $description): GymWallet
    {
        return $this->em->wrapInTransaction(fn() => $this->doDebit($gym, $amount, $reference, $description));
    }

    public function completeWithdrawal(WithdrawalRequest $withdrawal): void
    {
        $this->em->wrapInTransaction(function () use ($withdrawal): void {
            $wallet = $withdrawal->getGym()->getGymWallet();
            if (!$wallet) {
                throw new \RuntimeException('Wallet not found for gym #' . $withdrawal->getGym()->getId());
            }

            $balanceBefore = $wallet->getBalancePendingWithdrawal();
            $wallet->setBalancePendingWithdrawal($balanceBefore - $withdrawal->getAmount());
            $wallet->setUpdatedAt(new \DateTime());

            $tx = new WalletTransaction();
            $tx->setGym($withdrawal->getGym());
            $tx->setType(WalletTransaction::TYPE_DEBIT);
            $tx->setAmount($withdrawal->getAmount());
            $tx->setBalanceBefore($balanceBefore);
            $tx->setBalanceAfter($wallet->getBalancePendingWithdrawal());
            $tx->setReference($withdrawal->getFedapayTransferId());
            $tx->setDescription('Retrait finalisé — virement Mobile Money');
            $this->em->persist($tx);

            $withdrawal->setStatus(WithdrawalRequest::STATUS_COMPLETED);
            $withdrawal->setProcessedAt(new \DateTime());
            $this->em->persist($withdrawal);
        });
    }

    public function rejectWithdrawal(WithdrawalRequest $withdrawal, string $reason): void
    {
        $this->em->wrapInTransaction(function () use ($withdrawal, $reason): void {
            $wallet = $withdrawal->getGym()->getGymWallet();
            if (!$wallet) {
                throw new \RuntimeException('Wallet not found for gym #' . $withdrawal->getGym()->getId());
            }

            $wallet->setBalanceAvailable($wallet->getBalanceAvailable() + $withdrawal->getAmount());
            $wallet->setBalancePendingWithdrawal($wallet->getBalancePendingWithdrawal() - $withdrawal->getAmount());
            $wallet->setUpdatedAt(new \DateTime());

            $tx = new WalletTransaction();
            $tx->setGym($withdrawal->getGym());
            $tx->setType(WalletTransaction::TYPE_CREDIT);
            $tx->setAmount($withdrawal->getAmount());
            $tx->setBalanceBefore($wallet->getBalanceAvailable() - $withdrawal->getAmount());
            $tx->setBalanceAfter($wallet->getBalanceAvailable());
            $tx->setDescription('Remboursement retrait rejeté : ' . $reason);
            $this->em->persist($tx);

            $withdrawal->setStatus(WithdrawalRequest::STATUS_REJECTED);
            $withdrawal->setRejectionReason($reason);
            $withdrawal->setProcessedAt(new \DateTime());
            $this->em->persist($withdrawal);
        });
    }

    private function doCredit(Gym $gym, int $amount, string $reference, string $description, array $metadata = [], ?int $commissionOverride = null): GymWallet
    {
        $wallet = $this->getOrCreateWallet($gym);

        $rate = $commissionOverride ?? $this->commissionRate;
        $commission = (int) round($amount * $rate / 100);
        $netAmount = $amount - $commission;

        $balanceBefore = $wallet->getBalanceAvailable();

        // Commission transaction
        if ($commission > 0) {
            $commTx = new WalletTransaction();
            $commTx->setGym($gym);
            $commTx->setType(WalletTransaction::TYPE_COMMISSION);
            $commTx->setAmount($commission);
            $commTx->setBalanceBefore(0);
            $commTx->setBalanceAfter(0);
            $commTx->setReference($reference);
            $commTx->setDescription('Commission plateforme ' . $rate . '% sur ' . $description);
            $commTx->setMetadata($metadata);
            $this->em->persist($commTx);
        }

        // Credit transaction
        $wallet->setBalanceAvailable($balanceBefore + $netAmount);
        $wallet->setTotalEarned($wallet->getTotalEarned() + $netAmount);
        $wallet->setUpdatedAt(new \DateTime());

        $tx = new WalletTransaction();
        $tx->setGym($gym);
        $tx->setType(WalletTransaction::TYPE_CREDIT);
        $tx->setAmount($netAmount);
        $tx->setBalanceBefore($balanceBefore);
        $tx->setBalanceAfter($wallet->getBalanceAvailable());
        $tx->setReference($reference);
        $tx->setDescription($description);
        $tx->setMetadata($metadata);
        $this->em->persist($tx);

        return $wallet;
    }

    private function doDebit(Gym $gym, int $amount, string $reference, string $description): GymWallet
    {
        $wallet = $this->getOrCreateWallet($gym);

        if ($wallet->getBalanceAvailable() < $amount) {
            throw new InsufficientBalanceException();
        }

        $balanceBefore = $wallet->getBalanceAvailable();

        $wallet->setBalanceAvailable($balanceBefore - $amount);
        $wallet->setBalancePendingWithdrawal($wallet->getBalancePendingWithdrawal() + $amount);
        $wallet->setUpdatedAt(new \DateTime());

        $tx = new WalletTransaction();
        $tx->setGym($gym);
        $tx->setType(WalletTransaction::TYPE_WITHDRAWAL);
        $tx->setAmount($amount);
        $tx->setBalanceBefore($balanceBefore);
        $tx->setBalanceAfter($wallet->getBalanceAvailable());
        $tx->setReference($reference);
        $tx->setDescription($description);
        $this->em->persist($tx);

        return $wallet;
    }
}
