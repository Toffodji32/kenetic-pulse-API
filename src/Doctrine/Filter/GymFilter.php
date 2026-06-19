<?php

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class GymFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasAssociation('gym')) {
            return '';
        }

        $gymId = $this->getParameter('gym_id');

        if (!$gymId) {
            return '';
        }

        return sprintf('%s.gym_id = %s', $targetTableAlias, $gymId);
    }
}
