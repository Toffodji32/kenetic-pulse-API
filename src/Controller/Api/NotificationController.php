<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Security\GymResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_ADMIN')]
class NotificationController extends AbstractController
{
    public function __construct(
        private GymResolver $gymResolver,
        private NotificationRepository $notificationRepo,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $notifications = array_map(
            fn (Notification $n) => $this->serialize($n),
            $this->notificationRepo->findLatestByGym($gym)
        );

        return $this->json([
            'notifications' => $notifications,
            'unread_count'  => $this->notificationRepo->countUnreadByGym($gym),
        ]);
    }

    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        return $this->json(['count' => $this->notificationRepo->countUnreadByGym($gym)]);
    }

    #[Route('/{id}/read', name: 'api_notifications_read', methods: ['PATCH'])]
    public function markRead(int $id): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $notification = $this->notificationRepo->find($id);
        if (!$notification || $notification->getGym()->getId() !== $gym->getId()) {
            return new JsonResponse(['error' => 'Notification non trouvée'], 404);
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->em->flush();
        }

        return $this->json(['id' => $notification->getId(), 'is_read' => true]);
    }

    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['PATCH'])]
    public function markAllRead(): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return new JsonResponse(['error' => 'Gym non trouvée'], 404);
        }

        $updated = $this->notificationRepo->markAllReadByGym($gym);

        return $this->json(['updated' => $updated]);
    }

    private function serialize(Notification $n): array
    {
        return [
            'id'         => $n->getId(),
            'type'       => $n->getType(),
            'title'      => $n->getTitle(),
            'message'    => $n->getMessage(),
            'is_read'    => $n->isRead(),
            'created_at' => $n->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}