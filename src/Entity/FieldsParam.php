<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FieldsParamRepository")
 */
class FieldsParam
{
    const ENTITY_CODE_RECEPTION = 'réception';

    const FIELD_CODE_FOURNISSEUR = 'fournisseur';
    const FIELD_CODE_NUM_COMMANDE = 'numCommande';
    const FIELD_CODE_DATE_ATTENDUE = 'dateAttendue';
    const FIELD_CODE_DATE_COMMANDE = 'dateCommande';
    const FIELD_CODE_COMMENTAIRE = 'commentaire';
    const FIELD_CODE_ATTACHMENTS = 'attachment';
    const FIELD_CODE_UTILISATEUR = 'utilisateur';
    const FIELD_CODE_TRANSPORTEUR = 'transporteur';
    const FIELD_CODE_EMPLACEMENT = 'emplacement';
    const FIELD_CODE_ANOMALIE = 'anomalie';
    const FIELD_CODE_STORAGE_LOCATION = 'storageLocation';
    const FIELD_CODE_EMERGENCY_REC = 'manualUrgent';

    const FIELD_LABEL_FOURNISSEUR = 'fournisseur';
    const FIELD_LABEL_NUM_COMMANDE = 'numéro de commande';
    const FIELD_LABEL_DATE_ATTENDUE = 'date attendue';
    const FIELD_LABEL_DATE_COMMANDE = 'date commande';
    const FIELD_LABEL_COMMENTAIRE = 'commentaire';
    const FIELD_LABEL_ATTACHMENTS = 'pièces jointes';
    const FIELD_LABEL_UTILISATEUR = 'utilisateur';
	const FIELD_LABEL_TRANSPORTEUR = 'transporteur';
	const FIELD_LABEL_EMPLACEMENT = 'emplacement';
	const FIELD_LABEL_ANOMALIE = 'anomalie';
	const FIELD_LABEL_STORAGE_LOCATION = 'emplacement de stockage';
	const FIELD_LABEL_EMERGENCY_REC = 'urgence';

	const ENTITY_CODE_ARRIVAGE = 'arrivage';

    const FIELD_CODE_PROVIDER_ARRIVAGE = 'fournisseur';
    const FIELD_CODE_CARRIER_ARRIVAGE = 'transporteur';
    const FIELD_CODE_CHAUFFEUR_ARRIVAGE = 'chauffeur';
    const FIELD_CODE_NUMERO_TRACKING_ARRIVAGE = 'noTracking';
    const FIELD_CODE_NUM_COMMANDE_ARRIVAGE = 'numeroCommandeList';
    const FIELD_CODE_DROP_LOCATION_ARRIVAGE = 'dropLocation';
    const FIELD_CODE_TARGET_ARRIVAGE = 'destinataire';
    const FIELD_CODE_BUYERS_ARRIVAGE = 'acheteurs';
    const FIELD_CODE_PRINT_ARRIVAGE = 'imprimerArrivage';
    const FIELD_CODE_COMMENTAIRE_ARRIVAGE = 'commentaire';
    const FIELD_CODE_PJ_ARRIVAGE = 'pj';
    const FIELD_CODE_CUSTOMS_ARRIVAGE = 'customs';
    const FIELD_CODE_FROZEN_ARRIVAGE = 'frozen';
    const FIELD_CODE_PROJECT_NUMBER = 'projectNumber';
    const FIELD_CODE_BUSINESS_UNIT = 'businessUnit';

    const FIELD_LABEL_PROVIDER_ARRIVAGE = 'fournisseur';
    const FIELD_LABEL_CARRIER_ARRIVAGE = 'transporteur';
    const FIELD_LABEL_CHAUFFEUR_ARRIVAGE = 'chauffeur';
    const FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE = 'numéro tracking transporteur';
    const FIELD_LABEL_NUM_BL_ARRIVAGE = 'n° commande / BL';
    const FIELD_LABEL_TARGET_ARRIVAGE = 'destinataire';
    const FIELD_LABEL_BUYERS_ARRIVAGE = 'acheteurs';
    const FIELD_LABEL_PRINT_ARRIVAGE = 'imprimer arrivage';
    const FIELD_LABEL_COMMENTAIRE_ARRIVAGE = 'commentaire';
    const FIELD_LABEL_PJ_ARRIVAGE = 'Pièces jointes';
	const FIELD_LABEL_CUSTOMS_ARRIVAGE = 'douane';
	const FIELD_LABEL_FROZEN_ARRIVAGE = 'congelé';
    const FIELD_LABEL_PROJECT_NUMBER = 'numéro projet';
    const FIELD_LABEL_BUSINESS_UNIT = 'business unit';
    const FIELD_LABEL_DROP_LOCATION_ARRIVAGE = 'emplacement de dépose';

    const ENTITY_CODE_DISPATCH = 'acheminements';

    const FIELD_CODE_CARRIER_DISPATCH = 'carrier';
    const FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH = 'carrierTrackingNumber';
    const FIELD_CODE_RECEIVER_DISPATCH = 'receiver';
    const FIELD_CODE_DEADLINE_DISPATCH = 'deadline';
    const FIELD_CODE_EMERGENCY = 'emergency';
    const FIELD_CODE_COMMAND_NUMBER_DISPATCH = 'commandNumber';
    const FIELD_CODE_COMMENT_DISPATCH = 'comment';
    const FIELD_CODE_ATTACHMENTS_DISPATCH = 'attachments';
    const FIELD_CODE_LOCATION_PICK = 'pickLocation';
    const FIELD_CODE_LOCATION_DROP = 'dropLocation';

    const FIELD_LABEL_CARRIER_DISPATCH = 'transporteur';
    const FIELD_LABEL_CARRIER_TRACKING_NUMBER_DISPATCH = 'numéro de tracking transporteur';
    const FIELD_LABEL_RECEIVER_DISPATCH = 'destinataire';
    const FIELD_LABEL_DEADLINE_DISPATCH = 'dates d\'échéances';
    const FIELD_LABEL_EMERGENCY = 'urgence';
    const FIELD_LABEL_COMMAND_NUMBER_DISPATCH = 'numéro de commande';
    const FIELD_LABEL_COMMENT_DISPATCH = 'commentaire';
    const FIELD_LABEL_ATTACHMENTS_DISPATCH = 'pièces jointes';
    const FIELD_LABEL_LOCATION_PICK = 'emplacement de prise';
    const FIELD_LABEL_LOCATION_DROP = 'emplacement de dépose';

    const ENTITY_CODE_HANDLING = 'services';

    const FIELD_CODE_LOADING_ZONE = 'loadingZone';
    const FIELD_CODE_UNLOADING_ZONE = 'unloadingZone';
    const FIELD_CODE_CARRIED_OUT_OPERATION_COUNT = 'carriedOutOperationCount';
    const FIELD_CODE_RECEIVERS_HANDLING = 'receivers';

    const FIELD_LABEL_LOADING_ZONE = 'chargement';
    const FIELD_LABEL_UNLOADING_ZONE = 'déchargement';
    const FIELD_LABEL_CARRIED_OUT_OPERATION_COUNT = 'nombre d\'opération(s) réalisée(s)';
    const FIELD_LABEL_RECEIVERS_HANDLING = 'destinataires';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $entityCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fieldCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fieldLabel;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mustToCreate;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mustToModify;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $displayedFormsCreate;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $displayedFormsEdit;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $displayedFilters;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $elements = [];

    /**
     * @ORM\Column(type="boolean", nullable=true, options={"default": false})
     */
    private $fieldRequiredHidden;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityCode(): ?string
    {
        return $this->entityCode;
    }

    public function setEntityCode(string $entityCode): self
    {
        $this->entityCode = $entityCode;

        return $this;
    }

    public function getFieldCode(): ?string
    {
        return $this->fieldCode;
    }

    public function setFieldCode(string $fieldCode): self
    {
        $this->fieldCode = $fieldCode;

        return $this;
    }

    public function getMustToCreate(): ?bool
    {
        return $this->mustToCreate;
    }

    public function setMustToCreate(?bool $mustToCreate): self
    {
        $this->mustToCreate = $mustToCreate;

        return $this;
    }

    public function getMustToModify(): ?bool
    {
        return $this->mustToModify;
    }

    public function setMustToModify(?bool $mustToModify): self
    {
        $this->mustToModify = $mustToModify;

        return $this;
    }

    public function getFieldLabel(): ?string
    {
        return $this->fieldLabel;
    }

    public function setFieldLabel(string $fieldLabel): self
    {
        $this->fieldLabel = $fieldLabel;

        return $this;
    }

    public function isDisplayedFormsCreate(): ?bool {
        return $this->displayedFormsCreate;
    }

    public function setDisplayedFormsCreate(?bool $displayedFormsCreate): self {
        $this->displayedFormsCreate = $displayedFormsCreate;
        return $this;
    }

    public function isDisplayedFormsEdit(): ?bool {
        return $this->displayedFormsEdit;
    }

    public function setDisplayedFormsEdit(?bool $displayedFormsEdit): self {
        $this->displayedFormsEdit = $displayedFormsEdit;
        return $this;
    }

    public function isDisplayedFilters(): ?bool {
        return $this->displayedFilters;
    }

    public function setDisplayedFilters(?bool $displayedFilters): self {
        $this->displayedFilters = $displayedFilters;
        return $this;
    }

    public function getElements(): ?array {
        return $this->elements;
    }

    public function setElements(?array $elements): self {
        $this->elements = $elements;
        return $this;
    }

    public function getFieldRequiredHidden(): ?bool
    {
        return $this->fieldRequiredHidden;
    }

    public function setFieldRequiredHidden(?bool $fieldRequiredHidden): self
    {
        $this->fieldRequiredHidden = $fieldRequiredHidden;

        return $this;
    }
}
