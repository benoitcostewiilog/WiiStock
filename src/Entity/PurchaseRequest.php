<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\PurchaseRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * @ORM\Entity(repositoryClass=PurchaseRequestRepository::class)
 */
class PurchaseRequest
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $creationDate = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $validationDate = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $processingDate = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $considerationDate = null;

    /**
     * @ORM\ManyToOne(targetEntity=Utilisateur::class, inversedBy="purchaseRequestRequesters")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Utilisateur $requester = null;

    /**
     * @ORM\ManyToOne(targetEntity=Utilisateur::class, inversedBy="purchaseRequestBuyers")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Utilisateur $buyer = null;

    /**
     * @ORM\ManyToOne(targetEntity=Statut::class, inversedBy="purchaseRequests")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Statut $status = null;

    /**
     * @ORM\OneToMany(targetEntity=PurchaseRequestLine::class, mappedBy="purchaseRequest")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Collection $purchaseRequestLines;


    public function __construct()
    {
        $this->purchaseRequestLines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self
    {
        if($this->requester && $this->requester !== $requester){
            $this->requester->removePurchaseRequestRequesters($this);
        }
        $this->requester = $requester;
        if($requester) {
            $requester->addPurchaseRequestRequesters($this);
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getBuyer(): ?Utilisateur
    {
        return $this->buyer;
    }

    public function setBuyer(?Utilisateur $buyer): self
    {
        if($this->buyer && $this->buyer !== $buyer){
            $this->buyer->removePurchaseRequestBuyers($this);
        }
        $this->buyer = $buyer;
        if($buyer) {
            $buyer->addPurchaseRequestBuyers($this);
        }

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self
    {
        if($this->status && $this->status !== $status){
            $this->status->removePurchaseRequest($this);
        }
        $this->status = $status;
        if($status) {
            $status->addPurchaseRequest($this);
        }

        return $this;
    }

    public function getCreationDate(): ?DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(?DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getValidationDate(): ?DateTimeInterface
    {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTimeInterface $validationDate): self
    {
        $this->validationDate = $validationDate;

        return $this;
    }

    public function getProcessingDate(): ?DateTimeInterface
    {
        return $this->processingDate;
    }

    public function setProcessingDate(?DateTimeInterface $processingDate): self
    {
        $this->processingDate = $processingDate;

        return $this;
    }

    public function getConsiderationDate(): ?DateTimeInterface
    {
        return $this->considerationDate;
    }

    public function setConsiderationDate(?DateTimeInterface $considerationDate): self
    {
        $this->considerationDate = $considerationDate;

        return $this;
    }

    /**
     * @return Collection|purchaseRequestLine[]
     */
    public function getPurchaseRequestLines(): Collection {
        return $this->purchaseRequestLines;
    }

    public function addPurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if (!$this->purchaseRequestLines->contains($purchaseRequestLine)) {
            $this->purchaseRequestLines[] = $purchaseRequestLine;
            $purchaseRequestLine->setPurchaseRequest($this);
        }

        return $this;
    }

    public function removePurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if ($this->purchaseRequestLines->removeElement($purchaseRequestLine)) {
            if ($purchaseRequestLine->getPurchaseRequest() === $this) {
                $purchaseRequestLine->setPurchaseRequest(null);
            }
        }

        return $this;
    }

    public function setPurchaseRequestLines(?array $purchaseRequestLines): self {
        foreach($this->getPurchaseRequestLines()->toArray() as $purchaseRequestLine) {
            $this->removePurchaseRequestLine($purchaseRequestLine);
        }

        $this->purchaseRequestLines = new ArrayCollection();
        foreach($purchaseRequestLines as $purchaseRequestLine) {
            $this->addPurchaseRequestLine($purchaseRequestLine);
        }

        return $this;
    }
}
