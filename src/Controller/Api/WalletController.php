<?php

namespace App\Controller\Api;

use App\Entity\WalletTransaction;
use App\Repository\WalletTransactionRepository;
use App\Repository\WithdrawalRequestRepository;
use App\Security\GymResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/wallet')]
#[IsGranted('ROLE_ADMIN')]
class WalletController extends AbstractController
{
    public function __construct(
        private GymResolver $gymResolver,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_wallet_show', methods: ['GET'])]
    public function show(WithdrawalRequestRepository $withdrawalRepo): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return $this->json(['error' => 'Aucune salle associée'], 400);
        }

        $wallet = $gym->getGymWallet();
        if (!$wallet) {
            return $this->json([
                'balanceAvailable' => 0,
                'balancePending' => 0,
                'balancePendingWithdrawal' => 0,
                'totalEarned' => 0,
                'currency' => 'XOF',
                'updatedAt' => null,
                'pendingWithdrawal' => null,
            ]);
        }

        $pending = $withdrawalRepo->findOneBy([
            'gym' => $gym,
            'status' => ['pending', 'processing'],
        ], ['requestedAt' => 'DESC']);

        return $this->json([
            'balanceAvailable' => $wallet->getBalanceAvailable(),
            'balancePending' => $wallet->getBalancePending(),
            'balancePendingWithdrawal' => $wallet->getBalancePendingWithdrawal(),
            'totalEarned' => $wallet->getTotalEarned(),
            'currency' => $wallet->getCurrency(),
            'updatedAt' => $wallet->getUpdatedAt()?->format('c'),
            'pendingWithdrawal' => $pending ? [
                'id' => $pending->getId(),
                'amount' => $pending->getAmount(),
                'status' => $pending->getStatus(),
                'requestedAt' => $pending->getRequestedAt()->format('c'),
            ] : null,
        ]);
    }

    #[Route('/transactions', name: 'api_wallet_transactions', methods: ['GET'])]
    public function transactions(Request $request, WalletTransactionRepository $repo): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return $this->json(['error' => 'Aucune salle associée'], 400);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $qb = $repo->createQueryBuilder('wt')
            ->where('wt.gym = :gym')
            ->setParameter('gym', $gym)
            ->orderBy('wt.createdAt', 'DESC');

        $total = (clone $qb)->select('COUNT(wt.id)')->getQuery()->getSingleScalarResult();

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $data = array_map(fn(WalletTransaction $tx) => [
            'id' => $tx->getId(),
            'type' => $tx->getType(),
            'amount' => $tx->getAmount(),
            'balanceBefore' => $tx->getBalanceBefore(),
            'balanceAfter' => $tx->getBalanceAfter(),
            'reference' => $tx->getReference(),
            'description' => $tx->getDescription(),
            'createdAt' => $tx->getCreatedAt()->format('c'),
        ], $items);

        return $this->json([
            'data' => $data,
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $total,
            'pages' => (int) ceil($total / $limit),
        ]);
    }
}
