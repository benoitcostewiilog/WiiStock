<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParametreRepository")
 */
class Parametre
{
	const TYPE_BOOL = 'bool';
	const TYPE_TEXT = 'text';
	const TYPE_NUMBER = 'number';
	const TYPE_LIST = 'list';

	const LABEL_AJOUT_QUANTITE = 'ajout quantité';
	const VALUE_PAR_REF = 'par référence';
	const VALUE_PAR_ART = 'par article';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $typage;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $elements = [];

	/**
	 * @ORM\Column(type="string", length=255)
	 */
    private $defaultValue;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\ParametreRole", mappedBy="parametre")
	 */
	private $parametreRoles;

    public function __construct()
    {
        $this->parametreRoles = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getElements()
    {
        return $this->elements;
    }

    public function setElements($elements): self
    {
        $this->elements = $elements;

        return $this;
    }

    /**
     * @return Collection|ParametreRole[]
     */
    public function getParametreRoles(): Collection
    {
        return $this->parametreRoles;
    }

    public function addParametreRole(ParametreRole $parametreRole): self
    {
        if (!$this->parametreRoles->contains($parametreRole)) {
            $this->parametreRoles[] = $parametreRole;
            $parametreRole->setParametre($this);
        }

        return $this;
    }

    public function removeParametreRole(ParametreRole $parametreRole): self
    {
        if ($this->parametreRoles->contains($parametreRole)) {
            $this->parametreRoles->removeElement($parametreRole);
            // set the owning side to null (unless already changed)
            if ($parametreRole->getParametre() === $this) {
                $parametreRole->setParametre(null);
            }
        }

        return $this;
    }

    public function getTypage(): ?string
    {
        return $this->typage;
    }

    public function setTypage(string $typage): self
    {
        $this->typage = $typage;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(string $defaultValue): self
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }
}
