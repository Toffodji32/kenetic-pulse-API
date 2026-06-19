<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\Column(length: 100)]
    private ?string $firstName = null;


    #[ORM\Column(length: 100)]
    private ?string $lastName = null;


    #[ORM\Column(length: 20)]
    private ?string $phone = null;


    #[ORM\Column(length: 100)]
    private ?string $email = null;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCode = null;


    #[ORM\Column(length: 255, unique: true)]
    private ?string $uuid = null;


    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $registrationDate = null;


    #[ORM\Column(nullable: true)]
    private ?bool $isActive = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gym $gym = null;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'client')]
    private Collection $subscriptions;


    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'client')]
    private Collection $payments;


    /**
     * @var Collection<int, Checkin>
     */
    #[ORM\OneToMany(targetEntity: Checkin::class, mappedBy: 'client')]
    private Collection $checkins;


    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'client')]
    private Collection $orders;



    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->checkins = new ArrayCollection();
        $this->orders = new ArrayCollection();
    }



    public function getId(): ?int
    {
        return $this->id;
    }



    public function getFirstName(): ?string
    {
        return $this->firstName;
    }



    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }



    public function getLastName(): ?string
    {
        return $this->lastName;
    }



    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }



    public function getPhone(): ?string
    {
        return $this->phone;
    }



    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

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



    public function getPhoto(): ?string
    {
        return $this->photo;
    }



    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }



    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }



    public function setQrCode(?string $qrCode): static
    {
        $this->qrCode = $qrCode;

        return $this;
    }



    public function getUuid(): ?string
    {
        return $this->uuid;
    }



    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }



    public function getRegistrationDate(): ?\DateTimeInterface
    {
        return $this->registrationDate;
    }



    public function setRegistrationDate(\DateTimeInterface $registrationDate): static
    {
        $this->registrationDate = $registrationDate;

        return $this;
    }



    public function isActive(): ?bool
    {
        return $this->isActive;
    }



    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }



    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }



    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setClient($this);
        }

        return $this;
    }



    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getClient() === $this) {
                $subscription->setClient(null);
            }
        }

        return $this;
    }



    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }



    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setClient($this);
        }

        return $this;
    }



    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getClient() === $this) {
                $payment->setClient(null);
            }
        }

        return $this;
    }



    /**
     * @return Collection<int, Checkin>
     */
    public function getCheckins(): Collection
    {
        return $this->checkins;
    }



    public function addCheckin(Checkin $checkin): static
    {
        if (!$this->checkins->contains($checkin)) {
            $this->checkins->add($checkin);
            $checkin->setClient($this);
        }

        return $this;
    }



    public function removeCheckin(Checkin $checkin): static
    {
        if ($this->checkins->removeElement($checkin)) {
            if ($checkin->getClient() === $this) {
                $checkin->setClient(null);
            }
        }

        return $this;
    }



    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }



    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setClient($this);
        }

        return $this;
    }



    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getClient() === $this) {
                $order->setClient(null);
            }
        }

        return $this;
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
}