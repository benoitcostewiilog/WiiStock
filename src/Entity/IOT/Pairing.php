<?php

namespace App\Entity\IOT;

use App\Entity\LocationGroup;
use App\Repository\IOT\PairingRepository;
use App\Entity\Emplacement;
use App\Entity\Pack;
use App\Entity\Preparation;
use App\Entity\OrdreCollecte;
use App\Entity\Article;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;

/**
 * @ORM\Entity(repositoryClass=PairingRepository::class)
 */
class Pairing
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class, inversedBy="pairings")
     */
    private ?Emplacement $location = null;

    /**
     * @ORM\ManyToOne(targetEntity=LocationGroup::class, inversedBy="pairings")
     */
    private ?LocationGroup $locationGroup = null;

    /**
     * @ORM\ManyToOne(targetEntity=Article::class, inversedBy="pairings")
     */
    private ?Article $article = null;

    /**
     * @ORM\ManyToOne(targetEntity=Pack::class, inversedBy="pairings")
     */
    private ?Pack $pack = null;

    /**
     * @ORM\ManyToOne(targetEntity=Preparation::class, inversedBy="pairings")
     */
    private ?Preparation $preparationOrder = null;

    /**
     * @ORM\ManyToOne(targetEntity=OrdreCollecte::class, inversedBy="pairings")
     */
    private ?OrdreCollecte $collectOrder = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTimeInterface $start = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $end = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $active = null;

    /**
     * @ORM\ManyToOne(targetEntity=SensorWrapper::class, inversedBy="pairings")
     */
    private ?SensorWrapper $sensorWrapper = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setSensorWrapper(?SensorWrapper $sensorWrapper): self {
        if($this->sensorWrapper && $this->sensorWrapper !== $sensorWrapper) {
            $this->sensorWrapper->removePairing($this);
        }
        $this->sensorWrapper = $sensorWrapper;
        if($sensorWrapper) {
            $sensorWrapper->addPairing($this);
        }

        return $this;
    }

    public function getSensorWrapper(): ?SensorWrapper {
        return $this->sensorWrapper;
    }

    public function setLocation(?Emplacement $location): self {
        if($this->location && $this->location !== $location) {
            $this->location->removePairing($this);
        }
        $this->location = $location;
        if($location) {
            $location->addPairing($this);
        }

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocationGroup(?LocationGroup $locationGroup): self {
        if($this->locationGroup && $this->locationGroup !== $locationGroup) {
            $this->locationGroup->removePairing($this);
        }
        $this->locationGroup = $locationGroup;
        if($locationGroup) {
            $locationGroup->addPairing($this);
        }

        return $this;
    }

    public function getLocationGroup(): ?LocationGroup {
        return $this->locationGroup;
    }

    public function setArticle(?Article $article): self {
        if($this->article && $this->article !== $article) {
            $this->article->removePairing($this);
        }
        $this->article = $article;
        if($article) {
            $article->addPairing($this);
        }

        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setPack(?Pack $pack): self {
        if($this->pack && $this->pack !== $pack) {
            $this->pack->removePairing($this);
        }
        $this->pack = $pack;
        if($pack) {
            $pack->addPairing($this);
        }

        return $this;
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPreparationOrder(?Preparation $preparationOrder): self {
        if($this->preparationOrder && $this->preparationOrder !== $preparationOrder) {
            $this->preparationOrder->removePairing($this);
        }
        $this->preparationOrder = $preparationOrder;
        if($preparationOrder) {
            $preparationOrder->addPairing($this);
        }

        return $this;
    }

    public function getPreparationOrder(): ?Preparation {
        return $this->preparationOrder;
    }

    public function setCollectOrder(?OrdreCollecte $collectOrder): self {
        if($this->collectOrder && $this->collectOrder !== $collectOrder) {
            $this->collectOrder->removePairing($this);
        }
        $this->collectOrder = $collectOrder;
        if($collectOrder) {
            $collectOrder->addPairing($this);
        }

        return $this;
    }

    public function getCollectOrder(): ?OrdreCollecte {
        return $this->collectOrder;
    }

    public function getStart(): ?DateTimeInterface
    {
        return $this->start;
    }

    public function setStart(DateTimeInterface $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?DateTimeInterface
    {
        return $this->end;
    }

    public function setEnd(?DateTimeInterface $end): self
    {
        $this->end = $end;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function setEntity($entity) {

        if($entity instanceof Emplacement) {
            $this->setLocation($entity);
        } else if($entity instanceof Article) {
            $this->setArticle($entity);
        } else if($entity instanceof Pack) {
            $this->setPack($entity);
        } else if($entity instanceof Preparation) {
            $this->setPreparationOrder($entity);
        } else if($entity instanceof OrdreCollecte) {
            $this->setCollectOrder($entity);
        }
    }

    public function getEntity() {
        if($this->getLocation() !== null) {
            return $this->location;
        } else if($this->getArticle() !== null) {
            return $this->article;
        } else if($this->getPack() !== null){
            return $this->pack;
        } else if($this->getPreparationOrder() !== null) {
            return $this->preparationOrder;
        } else if($this->getCollectOrder() !== null) {
            return $this->collectOrder;
        } else {
            return '';
        }
    }
}
