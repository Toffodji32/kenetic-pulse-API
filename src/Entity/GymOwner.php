<?php

namespace App\Entity;

use App\Repository\GymOwnerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GymOwnerRepository::class)]
class GymOwner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\OneToMany(targetEntity: Gym::class, mappedBy: 'gymOwner')]
    private Collection $gyms;

    public function __construct()
    {
        $this->gyms = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getGyms(): Collection
    {
        return $this->gyms;
    }

    public function addGym(Gym $gym): static
    {
        if (!$this->gyms->contains($gym)) {
            $this->gyms->add($gym);
            $gym->setGymOwner($this);
        }
        return $this;
    }

    public function removeGym(Gym $gym): static
    {
        if ($this->gyms->removeElement($gym)) {
            if ($gym->getGymOwner() === $this) {
                $gym->setGymOwner(null);
            }
        }
        return $this;
    }
}
