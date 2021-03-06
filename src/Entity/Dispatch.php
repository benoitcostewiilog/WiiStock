<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DispatchRepository")
 */
class Dispatch extends FreeFieldEntity
{
    const CATEGORIE = 'acheminements';

    const PREFIX_NUMBER = 'A';


    /**
     * @var [string => bool] Associate field name to bool, if TRUE we saved it in user entity
     */
    const DELIVERY_NOTE_DATA = [
        'consignor' => true,
        'deliveryAddress' => false,
        'deliveryNumber' => false,
        'deliveryDate' => false,
        'dispatchEmergency' => false,
        'packs' => false,
        'salesOrderNumber' => false,
        'wayBill' => false,
        'customerPONumber' => false,
        'customerPODate' => false,
        'respOrderNb' => false,
        'projectNumber' => false,
        'username' => false,
        'userPhone' => false,
        'userFax' => false,
        'buyer' => false,
        'buyerPhone' => false,
        'buyerFax' => false,
        'invoiceNumber' => false,
        'soldNumber' => false,
        'invoiceTo' => false,
        'soldTo' => false,
        'endUserNo' => false,
        'deliverNo' => false,
        'endUser' => false,
        'deliverTo' => false,
        'consignor2' => true,
        'date' => false,
        'notes' => true,
    ];
    /**
     * @var [string => bool] Associate field name to bool, if TRUE we saved it in user entity
     */
    const WAYBILL_DATA = [
        'carrier' => false,
        'dispatchDate' => false,
        'consignor' => false,
        'receiver' => false,
        'consignorUsername' => false,
        'consignorEmail' => false,
        'receiverUsername' => false,
        'receiverEmail' => false,
        'locationFrom' => true,
        'locationTo' => true,
        'notes' => true
    ];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $carrierTrackingNumber;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $commandNumber;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $emergency;

    /**
     * @var DateTime|null
     * @ORM\Column(type="date", nullable=true)
     */
    private $startDate;

    /**
     * @var DateTime|null
     * @ORM\Column(type="date", nullable=true)
     */
    private $endDate;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $projectNumber;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $businessUnit;

    /**
     * @var Utilisateur|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="treatedDispatches")
     */
    private $treatedBy;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="dispatches")
     * @ORM\JoinColumn(nullable=false)
     */
    private $statut;

    /**
     * @ORM\OneToMany(targetEntity="Attachment", mappedBy="dispatch")
     */
    private $attachements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="dispatchesFrom")
     */
    private $locationFrom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="dispatchesTo")
     */
    private $locationTo;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DispatchPack", mappedBy="dispatch", orphanRemoval=true)
     */
    private $dispatchPacks;

    /**
     * @ORM\OneToMany(targetEntity=TrackingMovement::class, mappedBy="dispatch")
     */
    private $trackingMovements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="dispatches")
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $number;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validationDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $treatmentDate;

    /**
     * @var array|null
     * @ORM\Column(type="json", nullable=true)
     */
    private $waybillData;

    /**
     * @var array|null
     * @ORM\Column(type="json", nullable=true)
     */
    private $deliveryNoteData;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", inversedBy="receivedDispatches")
     * @ORM\JoinTable(name="dispatch_receiver")
     */
    private $receivers;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="requestedDispatches")
     * @ORM\JoinColumn(nullable=false)
     */
    private $requester;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transporteur", inversedBy="dispatches")
     * @ORM\JoinColumn(nullable=true)
     */
    private $carrier;

    public function __construct()
    {
        $this->dispatchPacks = new ArrayCollection();
        $this->attachements = new ArrayCollection();
        $this->waybillData = [];
        $this->deliveryNoteData = [];
        $this->receivers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $date): self
    {
        $this->creationDate = $date;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getReceivers(): ?Collection
    {
        return $this->receivers;
    }

    public function addReceiver(?Utilisateur $receiver): self
    {
        if(!$this->receivers->contains($receiver)) {
            $this->receivers[] = $receiver;
            if(!$receiver->getReceivedDispatches()->contains($this)) {
                $receiver->addReceivedDispatch($this);
            }
        }
        return $this;
    }

    public function removeReceiver(Utilisateur $receiver): self
    {
        if ($this->receivers->removeElement($receiver)) {
            $receiver->removeReceivedDispatch($this);
        }
        return $this;
    }

    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self
    {
        $this->requester = $requester;

        return $this;
    }

    public function getTreatedBy(): ?Utilisateur
    {
        return $this->treatedBy;
    }

    public function setTreatedBy(?Utilisateur $treatedBy): self
    {
        $this->treatedBy = $treatedBy;

        return $this;
    }


    public function getCarrier(): ?Transporteur
    {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self
    {
        $this->carrier = $carrier;
        return $this;
    }

    public function getCarrierTrackingNumber(): ?string {
        return $this->carrierTrackingNumber;
    }

    public function setCarrierTrackingNumber(?string $carrierTrackingNumber): self {
        $this->carrierTrackingNumber = $carrierTrackingNumber;
        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCommandNumber(): ?string
    {
        return $this->commandNumber;
    }

    public function setCommandNumber(?string $commandNumber): self
    {
        $this->commandNumber = $commandNumber;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection
    {
        return $this->attachements;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachements->contains($attachment)) {
            $this->attachements[] = $attachment;
            $attachment->setDispatch($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachements->contains($attachment)) {
            $this->attachements->removeElement($attachment);
            // set the owning side to null (unless already changed)
            if ($attachment->getDispatch() === $this) {
                $attachment->setDispatch(null);
            }
        }

        return $this;
    }

    public function getLocationFrom(): ?Emplacement
    {
        return $this->locationFrom;
    }

    public function setLocationFrom(?Emplacement $locationFrom): self
    {
        $this->locationFrom = $locationFrom;

        return $this;
    }

    public function getLocationTo(): ?Emplacement
    {
        return $this->locationTo;
    }

    public function setLocationTo(?Emplacement $locationTo): self
    {
        $this->locationTo = $locationTo;

        return $this;
    }

    /**
     * @return Collection|DispatchPack[]
     */
    public function getDispatchPacks(): Collection
    {
        return $this->dispatchPacks;
    }

    public function addDispatchPack(DispatchPack $dispatchPack): self
    {
        if (!$this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks[] = $dispatchPack;
            $dispatchPack->setDispatch($this);
        }

        return $this;
    }

    public function removeDispatchPack(DispatchPack $dispatchPack): self
    {
        if ($this->dispatchPacks->contains($dispatchPack)) {
            $this->dispatchPacks->removeElement($dispatchPack);
            // set the owning side to null (unless already changed)
            if ($dispatchPack->getDispatch() === $this) {
                $dispatchPack->setDispatch(null);
            }
        }

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getEmergency(): ?string {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self {
        $this->emergency = $emergency;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getStartDate(): ?DateTime {
        return $this->startDate;
    }

    /**
     * @param DateTime|null $startDate
     * @return self
     */
    public function setStartDate(?DateTime $startDate): self {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getEndDate(): ?DateTime {
        return $this->endDate;
    }

    /**
     * @param DateTime|null $endDate
     * @return self
     */
    public function setEndDate(?DateTime $endDate): self {
        $this->endDate = $endDate;
        return $this;
    }

    public function getValidationDate(): ?\DateTimeInterface {
        return $this->validationDate;
    }

    public function setValidationDate(?\DateTimeInterface $validationDate): self {
        $this->validationDate = $validationDate;
        return $this;
    }

    public function getTreatmentDate(): ?\DateTimeInterface {
        return $this->treatmentDate;
    }

    public function setTreatmentDate(?\DateTimeInterface $treatmentDate): self {
        $this->treatmentDate = $treatmentDate;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getTrackingMovements(): Collection
    {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self
    {
        if (!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setDispatch($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self
    {
        if ($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getDispatch() === $this) {
                $trackingMovement->setDispatch(null);
            }
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getProjectNumber(): ?string {
        return $this->projectNumber;
    }

    /**
     * @param string|null $projectNumber
     * @return self
     */
    public function setProjectNumber(?string $projectNumber): self {
        $this->projectNumber = $projectNumber;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBusinessUnit(): ?string {
        return $this->businessUnit;
    }

    /**
     * @param string|null $businessUnit
     * @return self
     */
    public function setBusinessUnit(?string $businessUnit): self {
        $this->businessUnit = $businessUnit;
        return $this;
    }

    /**
     * @return array
     */
    public function getWaybillData(): array {
        return $this->waybillData ?? [];
    }

    /**
     * @param array $waybillData
     * @return self
     */
    public function setWaybillData(array $waybillData): self {
        $this->waybillData = $waybillData;
        return $this;
    }

    /**
     * @return array
     */
    public function getDeliveryNoteData(): array {
        return $this->deliveryNoteData ?? [];
    }

    /**
     * @param array $deliveryNoteData
     * @return self
     */
    public function setDeliveryNoteData(array $deliveryNoteData): self {
        $this->deliveryNoteData = $deliveryNoteData;
        return $this;
    }

}
