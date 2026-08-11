<?php

namespace App\Controller\Api;

use App\Entity\WithdrawalRequest;
use App\Repository\GymRepository;
use App\Repository\WalletTransactionRepository;
use App\Repository\WithdrawalRequestRepository;
use App\Service\MailerService;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/superadmin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class SuperAdminWalletController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private WalletService $walletService,
        private MailerService $mailerService,
    ) {}

    #[Route('/withdrawals', name: 'api_superadmin_withdrawals', methods: ['GET'])]
    public function withdrawals(Request $request, WithdrawalRequestRepository $repo): JsonResponse
    {
        $status = $request->query->get('status');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $qb = $repo->createQueryBuilder('wr')
            ->leftJoin('wr.gym', 'g')
            ->addSelect('g')
            ->orderBy('wr.requestedAt', 'DESC');

        if ($status) {
            $qb->where('wr.status = :status')->setParameter('status', $status);
        }

        $countQb = clone $qb;
        $total = $countQb->select('COUNT(wr.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $data = array_map(fn(WithdrawalRequest $w) => [
            'id' => $w->getId(),
            'gym' => $w->getGym() ? [
                'id' => $w->getGym()->getId(),
                'name' => $w->getGym()->getName(),
                'slug' => $w->getGym()->getSlug(),
            ] : null,
            'amount' => $w->getAmount(),
            'mobileMoneyNumber' => $w->getMobileMoneyNumber(),
            'mobileMoneyOperator' => $w->getMobileMoneyOperator(),
            'status' => $w->getStatus(),
            'requestedAt' => $w->getRequestedAt()->format('c'),
            'processedAt' => $w->getProcessedAt()?->format('c'),
            'rejectionReason' => $w->getRejectionReason(),
            'fedapayTransferId' => $w->getFedapayTransferId(),
        ], $items);

        return $this->json([
            'data' => $data,
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $total,
            'pages' => (int) ceil($total / $limit),
        ]);
    }

    #[Route('/withdrawals/{id}', name: 'api_superadmin_withdrawal_detail', methods: ['GET'])]
    public function withdrawalDetail(int $id, WithdrawalRequestRepository $repo): JsonResponse
    {
        $w = $repo->find($id);
        if (!$w) {
            return $this->json(['error' => 'Retrait non trouvé'], 404);
        }

        return $this->json([
            'id' => $w->getId(),
            'gym' => $w->getGym() ? [
                'id' => $w->getGym()->getId(),
                'name' => $w->getGym()->getName(),
                'slug' => $w->getGym()->getSlug(),
                'email' => $w->getGym()->getEmail(),
                'phone' => $w->getGym()->getPhone(),
                'wallet' => $w->getGym()->getGymWallet() ? [
                    'balanceAvailable' => $w->getGym()->getGymWallet()->getBalanceAvailable(),
                    'balancePendingWithdrawal' => $w->getGym()->getGymWallet()->getBalancePendingWithdrawal(),
                    'totalEarned' => $w->getGym()->getGymWallet()->getTotalEarned(),
                ] : null,
            ] : null,
            'amount' => $w->getAmount(),
            'mobileMoneyNumber' => $w->getMobileMoneyNumber(),
            'mobileMoneyOperator' => $w->getMobileMoneyOperator(),
            'status' => $w->getStatus(),
            'requestedAt' => $w->getRequestedAt()->format('c'),
            'processedAt' => $w->getProcessedAt()?->format('c'),
            'processedBy' => $w->getProcessedBy() ? [
                'id' => $w->getProcessedBy()->getId(),
                'name' => $w->getProcessedBy()->getName(),
                'email' => $w->getProcessedBy()->getEmail(),
            ] : null,
            'fedapayTransferId' => $w->getFedapayTransferId(),
            'rejectionReason' => $w->getRejectionReason(),
            'adminNotes' => $w->getAdminNotes(),
        ]);
    }

    #[Route('/wallets', name: 'api_superadmin_wallets', methods: ['GET'])]
    public function wallets(GymRepository $gymRepo): JsonResponse
    {
        $gyms = $gymRepo->findAll();
        $data = [];

        foreach ($gyms as $gym) {
            $wallet = $gym->getGymWallet();
            $data[] = [
                'gym' => [
                    'id' => $gym->getId(),
                    'name' => $gym->getName(),
                    'slug' => $gym->getSlug(),
                ],
                'balanceAvailable' => $wallet?->getBalanceAvailable() ?? 0,
                'balancePending' => $wallet?->getBalancePending() ?? 0,
                'balancePendingWithdrawal' => $wallet?->getBalancePendingWithdrawal() ?? 0,
                'totalEarned' => $wallet?->getTotalEarned() ?? 0,
                'currency' => $wallet?->getCurrency() ?? 'XOF',
                'updatedAt' => $wallet?->getUpdatedAt()?->format('c'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/wallet-stats', name: 'api_superadmin_wallet_stats', methods: ['GET'])]
    public function walletStats(GymRepository $gymRepo, WalletTransactionRepository $txRepo): JsonResponse
    {
        $gyms = $gymRepo->findAll();
        $totalCirculation = 0;
        $totalCommissions = 0;
        $totalVolume = 0;
        $totalPendingWithdrawals = 0;

        foreach ($gyms as $gym) {
            $wallet = $gym->getGymWallet();
            if ($wallet) {
                $totalCirculation += $wallet->getBalanceAvailable();
                $totalPendingWithdrawals += $wallet->getBalancePendingWithdrawal();
                $totalVolume += $wallet->getTotalEarned();
            }
        }

        $totalCommissions = (int) $txRepo->createQueryBuilder('tx')
            ->select('COALESCE(SUM(tx.amount), 0)')
            ->where('tx.type = :type')
            ->setParameter('type', 'commission')
            ->getQuery()
            ->getSingleScalarResult();

        $pendingCount = (int) $this->em->getRepository(WithdrawalRequest::class)
            ->count(['status' => WithdrawalRequest::STATUS_PENDING]);

        $pendingVolume = (int) $this->em->getRepository(WithdrawalRequest::class)
            ->createQueryBuilder('wr')
            ->select('COALESCE(SUM(wr.amount), 0)')
            ->where('wr.status = :status')
            ->setParameter('status', WithdrawalRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $completedThisMonth = (int) $this->em->getRepository(WithdrawalRequest::class)
            ->createQueryBuilder('wr')
            ->select('COUNT(wr.id)')
            ->where('wr.status = :status')
            ->andWhere('wr.processedAt >= :start')
            ->setParameter('status', WithdrawalRequest::STATUS_COMPLETED)
            ->setParameter('start', new \DateTime('first day of this month 00:00:00'))
            ->getQuery()
            ->getSingleScalarResult();

        $completedVolumeThisMonth = (int) $this->em->getRepository(WithdrawalRequest::class)
            ->createQueryBuilder('wr')
            ->select('COALESCE(SUM(wr.amount), 0)')
            ->where('wr.status = :status')
            ->andWhere('wr.processedAt >= :start')
            ->setParameter('status', WithdrawalRequest::STATUS_COMPLETED)
            ->setParameter('start', new \DateTime('first day of this month 00:00:00'))
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'totalWallets' => count($gyms),
            'totalCirculation' => $totalCirculation,
            'totalCommissions' => $totalCommissions,
            'totalVolume' => $totalVolume,
            'totalPendingWithdrawals' => $totalPendingWithdrawals,
            'pendingWithdrawals' => [
                'count' => $pendingCount,
                'volume' => $pendingVolume,
            ],
            'completedThisMonth' => [
                'count' => $completedThisMonth,
                'volume' => $completedVolumeThisMonth,
            ],
        ]);
    }

    #[Route('/withdrawals/{id}/approve', name: 'api_superadmin_withdrawal_approve', methods: ['POST'])]
    public function approveWithdrawal(int $id, WithdrawalRequestRepository $repo): JsonResponse
    {
        $withdrawal = $repo->find($id);
        if (!$withdrawal) {
            return $this->json(['error' => 'Retrait non trouvé'], 404);
        }

        if ($withdrawal->getStatus() !== WithdrawalRequest::STATUS_PENDING) {
            return $this->json(['error' => 'Ce retrait n\'est plus en attente'], 400);
        }

        $user = $this->getUser();

        $withdrawal->setStatus(WithdrawalRequest::STATUS_PROCESSING);
        $withdrawal->setProcessedBy($user);
        $withdrawal->setProcessedAt(new \DateTime());

        // Call FedaPay Transfer API
        try {
            $transferId = $this->callFedaPayTransfer($withdrawal);
            $withdrawal->setFedapayTransferId($transferId);
        } catch (\Exception $e) {
            $withdrawal->setStatus(WithdrawalRequest::STATUS_PENDING);
            $withdrawal->setProcessedAt(null);
            $withdrawal->setProcessedBy(null);
            $this->em->flush();
            return $this->json(['error' => 'Erreur FedaPay : ' . $e->getMessage()], 502);
        }

        $this->em->flush();

        $this->notifyGymWithdrawalProcessing($withdrawal);

        return $this->json([
            'id' => $withdrawal->getId(),
            'status' => $withdrawal->getStatus(),
            'fedapayTransferId' => $withdrawal->getFedapayTransferId(),
            'processedAt' => $withdrawal->getProcessedAt()?->format('c'),
        ]);
    }

    #[Route('/withdrawals/{id}/reject', name: 'api_superadmin_withdrawal_reject', methods: ['POST'])]
    public function rejectWithdrawal(int $id, Request $request, WithdrawalRequestRepository $repo): JsonResponse
    {
        $withdrawal = $repo->find($id);
        if (!$withdrawal) {
            return $this->json(['error' => 'Retrait non trouvé'], 404);
        }

        if ($withdrawal->getStatus() !== WithdrawalRequest::STATUS_PENDING) {
            return $this->json(['error' => 'Ce retrait n\'est plus en attente'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? '';
        if (empty($reason)) {
            return $this->json(['error' => 'La raison du rejet est obligatoire'], 400);
        }

        $this->walletService->rejectWithdrawal($withdrawal, $reason);
        $this->em->flush();

        $this->notifyGymWithdrawalRejected($withdrawal, $reason);

        return $this->json([
            'id' => $withdrawal->getId(),
            'status' => $withdrawal->getStatus(),
            'rejectionReason' => $withdrawal->getRejectionReason(),
        ]);
    }

    private function callFedaPayTransfer(WithdrawalRequest $withdrawal): string
    {
        $apiKey = $_ENV['FEDAPAY_SECRET_KEY'] ?? $_SERVER['FEDAPAY_SECRET_KEY'] ?? 'sk_sandbox_ymFzMM3g7lgDLLjNbte5txWx';
        $env = $_ENV['FEDAPAY_ENV'] ?? $_SERVER['FEDAPAY_ENV'] ?? 'sandbox';
        $baseUrl = $env === 'production'
            ? 'https://api.fedapay.com/v1'
            : 'https://sandbox-api.fedapay.com/v1';

        $gym = $withdrawal->getGym();
        $owner = $gym?->getGymOwner();
        $phone = preg_replace('/[^0-9]/', '', $withdrawal->getMobileMoneyNumber());
        $phone = preg_replace('/^229/', '', $phone);
        $country = $env === 'production' ? 'BJ' : 'BJ';

        // 1. Créer la transaction FedaPay
        $payload = [
            'description' => 'Retrait wallet Kinetic Pulse - ' . ($gym?->getName() ?? 'Gym'),
            'amount' => $withdrawal->getAmount(),
            'currency' => ['iso' => 'XOF'],
            'callback_url' => 'https://kenetic-pulse-api.onrender.com/api/webhooks/fedapay',
            'customer' => [
                'firstname' => $owner?->getName() ? explode(' ', $owner->getName())[0] : 'Client',
                'lastname' => $owner?->getName() && str_contains($owner->getName(), ' ')
                    ? substr($owner->getName(), strpos($owner->getName(), ' ') + 1)
                    : 'Kinetic',
                'email' => $owner?->getEmail() ?? 'client@kinetic-pulse.com',
                'phone_number' => [
                    'number' => $phone,
                    'country' => $country,
                ],
            ],
        ];

        $txData = $this->callFedaPay($baseUrl, $apiKey, 'POST', '/transactions', $payload);
        $transaction = $txData['v1/transaction'] ?? $txData['transaction'] ?? $txData;
        $txId = $transaction['id'] ?? null;
        if (!$txId) {
            throw new \RuntimeException('FedaPay: transaction non créée');
        }

        // 2. Générer le token de paiement
        $tokenData = $this->callFedaPay($baseUrl, $apiKey, 'POST', '/transactions/' . $txId . '/token', []);
        $token = $tokenData['token'] ?? null;
        if (!$token) {
            throw new \RuntimeException('FedaPay: token non généré');
        }

        // 3. Déclencher l'envoi Mobile Money
        // En sandbox, seuls les envois de test sont autorisés (momo_test)
        $mode = $env === 'production'
            ? ($withdrawal->getMobileMoneyOperator() === WithdrawalRequest::OPERATOR_MOOV ? 'moov' : 'mtn')
            : 'momo_test';
        $sendData = $this->callFedaPay($baseUrl, $apiKey, 'POST', '/' . $mode, [
            'token' => $token,
            'phone_number' => [
                'number' => $phone,
                'country' => $country,
            ],
        ]);

        $intent = $sendData['v1/payment_intent'] ?? $sendData['payment_intent'] ?? $sendData;
        $reference = $intent['reference'] ?? $intent['id'] ?? null;
        if (!$reference) {
            throw new \RuntimeException('FedaPay: réponse d\'envoi invalide');
        }

        return (string) $reference;
    }

    private function callFedaPay(string $baseUrl, string $apiKey, string $method, string $path, array $payload): array
    {
        $ch = curl_init($baseUrl . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ];
        if (!empty($payload)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('FedaPay: erreur réseau - ' . $curlError);
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new \RuntimeException('FedaPay a refusé (' . $method . ' ' . $path . ' HTTP ' . $httpCode . '): ' . substr((string) $response, 0, 500));
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('FedaPay: réponse JSON invalide');
        }

        return $data;
    }

    private function notifyGymWithdrawalProcessing(WithdrawalRequest $withdrawal): void
    {
        try {
            $gym = $withdrawal->getGym();
            $owner = $gym->getGymOwner();
            if (!$owner || !$owner->getEmail()) {
                return;
            }

            $html = sprintf(
                '<p>Votre demande de retrait de <strong>%d FCFA</strong> a été approuvée et est en cours de traitement.</p>
                 <p>Montant : %d FCFA</p>
                 <p>Numéro : %s</p>
                 <p>Le virement sera effectué sous 48h.</p>',
                $withdrawal->getAmount(),
                $withdrawal->getAmount(),
                htmlspecialchars($withdrawal->getMobileMoneyNumber())
            );

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
                ->to(new \Symfony\Component\Mime\Address($owner->getEmail(), $owner->getName()))
                ->subject('Kinetic Pulse — Retrait en cours de traitement')
                ->html($html);

            $this->mailerService->sendRaw($email);
        } catch (\Exception $e) {
            // Fail silently
        }
    }

    private function notifyGymWithdrawalRejected(WithdrawalRequest $withdrawal, string $reason): void
    {
        try {
            $gym = $withdrawal->getGym();
            $owner = $gym->getGymOwner();
            if (!$owner || !$owner->getEmail()) {
                return;
            }

            $html = sprintf(
                '<p>Votre demande de retrait de <strong>%d FCFA</strong> a été rejetée.</p>
                 <p><strong>Raison :</strong> %s</p>
                 <p>Le montant a été recrédité sur votre wallet.</p>',
                $withdrawal->getAmount(),
                htmlspecialchars($reason)
            );

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
                ->to(new \Symfony\Component\Mime\Address($owner->getEmail(), $owner->getName()))
                ->subject('Kinetic Pulse — Demande de retrait rejetée')
                ->html($html);

            $this->mailerService->sendRaw($email);
        } catch (\Exception $e) {
            // Fail silently
        }
    }
}
