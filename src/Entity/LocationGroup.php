<?php

namespace App\Entity;

use App\Repository\LocationGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LocationGroupRepository::class)
 */
class LocationGroup {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity=Emplacement::class, mappedBy="locationGroup")
     */
    private $locations;

    public function __construct() {
        $this->locations = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection|Emplacement[]
     */
    public function getLocations(): Collection {
        return $this->locations;
    }

    public function addLocation(Emplacement $location): self {
        if (!$this->locations->contains($location)) {
            $this->locations[] = $location;
            $location->setLocationGroup($this);
        }

        return $this;
    }

    public function removeLocation(Emplacement $location): self {
        if ($this->locations->removeElement($location)) {
            if ($location->getLocationGroup() === $this) {
                $location->setLocationGroup(null);
            }
        }

        return $this;
    }

    public function setLocations(?array $locations): self {
        foreach ($this->getLocations()->toArray() as $location) {
            $this->removeLocation($location);
        }

        $this->locations = new ArrayCollection();
        foreach ($locations as $location) {
            $this->addLocation($location);
        }

        return $this;
    }

}
