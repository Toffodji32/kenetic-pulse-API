<?php

namespace App\Controller\Api;

use App\Entity\WithdrawalRequest;
use App\Exception\InsufficientBalanceException;
use App\Repository\WithdrawalRequestRepository;
use App\Security\GymResolver;
use App\Service\MailerService;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/wallet')]
#[IsGranted('ROLE_ADMIN')]
class WithdrawalController extends AbstractController
{
    public function __construct(
        private WalletService $walletService,
        private GymResolver $gymResolver,
        private EntityManagerInterface $em,
        private MailerService $mailerService,
    ) {}

    #[Route('/withdraw', name: 'api_wallet_withdraw', methods: ['POST'])]
    public function withdraw(Request $request): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return $this->json(['error' => 'Aucune salle associée'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $amount = (int) ($data['amount'] ?? 0);
        $mobileMoneyNumber = $data['mobileMoneyNumber'] ?? '';
        $mobileMoneyOperator = $data['mobileMoneyOperator'] ?? '';

        // Validation: minimum amount
        $minAmount = (int) ($_ENV['WITHDRAWAL_MIN_AMOUNT'] ?? 1000);
        if ($amount < $minAmount) {
            return $this->json(['error' => "Le montant minimum de retrait est de {$minAmount} FCFA"], 400);
        }

        // Validation: operator
        if (!in_array($mobileMoneyOperator, [WithdrawalRequest::OPERATOR_MTN, WithdrawalRequest::OPERATOR_MOOV], true)) {
            return $this->json(['error' => 'Opérateur invalide. Utilisez mtn ou moov'], 400);
        }

        // Validation: number
        if (!preg_match('/^(\\+229|229)?[0-9]{8,10}$/', preg_replace('/[^0-9]/', '', $mobileMoneyNumber))) {
            return $this->json(['error' => 'Numéro Mobile Money invalide'], 400);
        }

        // Check no pending withdrawal
        $pendingRepo = $this->em->getRepository(WithdrawalRequest::class);
        $existingPending = $pendingRepo->findOneBy([
            'gym' => $gym,
            'status' => [WithdrawalRequest::STATUS_PENDING, WithdrawalRequest::STATUS_PROCESSING],
        ]);
        if ($existingPending) {
            return $this->json(['error' => 'Un retrait est déjà en cours de traitement. Veuillez attendre sa finalisation.'], 409);
        }

        // Check daily limit (max 1 completed withdrawal per day)
        $today = new \DateTime('today');
        $completedToday = $pendingRepo->createQueryBuilder('wr')
            ->select('COUNT(wr.id)')
            ->where('wr.gym = :gym')
            ->andWhere('wr.status = :status')
            ->andWhere('wr.requestedAt >= :today')
            ->setParameter('gym', $gym)
            ->setParameter('status', WithdrawalRequest::STATUS_COMPLETED)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $completedToday > 0) {
            return $this->json(['error' => 'Un retrait a déjà été effectué aujourd\'hui. Réessayez demain.'], 429);
        }

        // Create withdrawal request
        $withdrawal = new WithdrawalRequest();
        $withdrawal->setGym($gym);
        $withdrawal->setAmount($amount);
        $withdrawal->setMobileMoneyNumber($mobileMoneyNumber);
        $withdrawal->setMobileMoneyOperator($mobileMoneyOperator);
        $withdrawal->setStatus(WithdrawalRequest::STATUS_PENDING);

        try {
            $this->walletService->debit($gym, $amount, 'withdrawal-' . time(), 'Demande de retrait vers ' . $mobileMoneyNumber);
            $this->em->persist($withdrawal);
            $this->em->flush();
            $this->em->refresh($withdrawal);
        } catch (InsufficientBalanceException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        // Notify super admin
        $this->notifySuperAdmin($gym, $withdrawal);

        return $this->json([
            'id' => $withdrawal->getId(),
            'amount' => $withdrawal->getAmount(),
            'mobileMoneyNumber' => $withdrawal->getMobileMoneyNumber(),
            'mobileMoneyOperator' => $withdrawal->getMobileMoneyOperator(),
            'status' => $withdrawal->getStatus(),
            'requestedAt' => $withdrawal->getRequestedAt()->format('c'),
        ], 201);
    }

    #[Route('/withdrawals', name: 'api_wallet_withdrawals', methods: ['GET'])]
    public function withdrawals(WithdrawalRequestRepository $repo): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return $this->json(['error' => 'Aucune salle associée'], 400);
        }

        $list = $repo->findBy(['gym' => $gym], ['requestedAt' => 'DESC']);
        $data = array_map(fn(WithdrawalRequest $w) => [
            'id' => $w->getId(),
            'amount' => $w->getAmount(),
            'mobileMoneyNumber' => $w->getMobileMoneyNumber(),
            'mobileMoneyOperator' => $w->getMobileMoneyOperator(),
            'status' => $w->getStatus(),
            'requestedAt' => $w->getRequestedAt()->format('c'),
            'processedAt' => $w->getProcessedAt()?->format('c'),
            'rejectionReason' => $w->getRejectionReason(),
        ], $list);

        return $this->json($data);
    }

    private function notifySuperAdmin(\App\Entity\Gym $gym, WithdrawalRequest $withdrawal): void
    {
        try {
            $html = sprintf(
                '<h2>Nouvelle demande de retrait</h2>
                 <p><strong>Salle :</strong> %s</p>
                 <p><strong>Montant :</strong> %d FCFA</p>
                 <p><strong>Numéro Mobile Money :</strong> %s</p>
                 <p><strong>Opérateur :</strong> %s</p>
                 <p><strong>Date :</strong> %s</p>
                 <p><a href="%s/api/superadmin/withdrawals/%d">Traiter la demande</a></p>',
                htmlspecialchars($gym->getName()),
                $withdrawal->getAmount(),
                htmlspecialchars($withdrawal->getMobileMoneyNumber()),
                strtoupper($withdrawal->getMobileMoneyOperator()),
                $withdrawal->getRequestedAt()->format('d/m/Y H:i'),
                $_ENV['APP_BASE_URL'] ?? 'http://127.0.0.1:8000',
                $withdrawal->getId()
            );

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address('toffodjiatchade@gmail.com', 'Kinetic Pulse'))
                ->to(new \Symfony\Component\Mime\Address('toffodjiatchade@gmail.com', 'Super Admin'))
                ->subject('[Kinetic Pulse] Nouvelle demande de retrait — ' . $gym->getName() . ' — ' . number_format($withdrawal->getAmount(), 0, ',', ' ') . ' FCFA')
                ->html($html);

            $this->mailerService->sendRaw($email);
        } catch (\Exception $e) {
            $this->logger?->warning('Failed to notify super admin about withdrawal', [
                'withdrawal_id' => $withdrawal->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
