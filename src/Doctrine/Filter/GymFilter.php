<?php

namespace App\Doctrine\Filter;

use App\Entity\SubscriptionType;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class GymFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasAssociation('gym')) {
            return '';
        }

        // Shared entities that should NOT be scoped by gym
        if (in_array($targetEntity->getName(), [
            SubscriptionType::class,
        ])) {
            return '';
        }

        $gymId = $this->getParameter('gym_id');

        if (!$gymId) {
            return '';
        }

        return sprintf('%s.gym_id = %s', $targetTableAlias, $gymId);
    }
}
