<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Import;
use App\Entity\Litige;
use App\Entity\MouvementTraca;
use App\Entity\PieceJointe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpKernel\KernelInterface;


class AttachmentService
{
    const LOGO_FOR_LABEL = 'logo_for_label';

    private $attachmentDirectory;
	private $em;

    public function __construct(EntityManagerInterface $em,
                                KernelInterface $kernel) {
        $this->attachmentDirectory = $kernel->getProjectDir() . '/public/uploads/attachements';
    	$this->em = $em;
    }

	/**
	 * @param FileBag|UploadedFile[]|array $files if array it's an assoc array between originalFileName and serverFileName
	 * @param Arrivage|Litige|MouvementTraca|Import $entity
	 * @return PieceJointe[]
	 */
	public function addAttachements($files, $entity) {
		$attachments = [];

        if ($files instanceof FileBag) {
            $files = $files->all();
        }

        $isFileName = count($files) > 0 && is_string($files[array_key_first($files)]);
        foreach ($files as $fileIndex => $file) {
			if ($file) {
                if ($isFileName) {
                    $originalFileName = $fileIndex;
                    $fileName = $file;
                } else {
                    $fileArray = $this->saveFile($file);
                    $originalFileName = $file->getClientOriginalName();
                    $fileName = $fileArray[$file->getClientOriginalName()];
                }
                $pj = new PieceJointe();
                $pj
                    ->setOriginalName($originalFileName)
                    ->setFileName($fileName);
                $this->em->persist($pj);
                $attachments[] = $pj;

                if ($entity instanceof Arrivage) {
					$entity->addAttachement($pj);
				} elseif ($entity instanceof Litige) {
					$entity->addPiecesJointe($pj);
				} elseif ($entity instanceof MouvementTraca) {
					$entity->addAttachement($pj);
				} elseif ($entity instanceof Import) {
					$entity->setCsvFile($pj);
				}

                $this->em->flush();
			}
		}

        return $attachments;
	}

    /**
     * @param UploadedFile $file
     * @param string $wantedName
     * @return array [originalName (string) => filename (string)]
     */
	public function saveFile(UploadedFile $file, string $wantedName = null): array {
        if (!file_exists($this->attachmentDirectory)) {
            mkdir($this->attachmentDirectory, 0777);
        }

        $filename = ($wantedName ?? uniqid()) . '.' . $file->getClientOriginalExtension() ?? '';
        $file->move($this->attachmentDirectory, $filename);
        return [$file->getClientOriginalName() => $filename];
    }

	/**
	 * @param PieceJointe $attachment
	 * @param Arrivage $arrivage
	 * @param Litige $litige
	 * @param MouvementTraca $mvtTraca
	 */
	public function removeAndDeleteAttachment($attachment, $arrivage, $litige = null, $mvtTraca = null)
	{
		if ($arrivage) {
			$arrivage->removeAttachement($attachment);
		} elseif ($litige) {
			$litige->removeAttachement($attachment);
		} elseif ($mvtTraca) {
			$mvtTraca->removeAttachement($attachment);
		}

        $pieceJointeRepository = $this->em->getRepository(PieceJointe::class);
        $pieceJointeAlreadyInDB = $pieceJointeRepository->findOneByFileName($attachment->getFileName());
        if (count($pieceJointeAlreadyInDB) === 1) {
            $path = $this->getServerPath($attachment);
            unlink($path);
        }

        $this->em->remove($attachment);
        $this->em->flush();
	}

    /**
     * @param PieceJointe $attachment
     * @return string
     */
	public function getServerPath(PieceJointe $attachment): string {
	    return $this->attachmentDirectory . '/' . $attachment->getFileName();
    }

    /**
     * @param string $fileName
     * @param array $content
     * @param callable $mapper
     * @return void
     */
	public function saveCSVFile(string $fileName, array $content, callable $mapper): void {
        $csvFilePath = $this->attachmentDirectory . '/' . $fileName;

        $logCsvFilePathOpened = fopen($csvFilePath, 'w');

        foreach ($content as $row) {
            fputcsv($logCsvFilePathOpened, $mapper($row), ';');
        }

        fclose($logCsvFilePathOpened);
    }

    /**
     * @param UploadedFile $file
     * @param Arrivage|Litige $link
     * @return PieceJointe
     */
    public function createPieceJointe(UploadedFile $file, $link): PieceJointe {
        if ($file->getClientOriginalExtension()) {
            $filename = uniqid() . "." . $file->getClientOriginalExtension();
        } else {
            $filename = uniqid();
        }
        $file->move($this->attachmentDirectory, $filename);

        $pj = new PieceJointe();
        $pj
            ->setFileName($filename)
            ->setOriginalName($file->getClientOriginalName());

        if ($link instanceof Arrivage) {
            $pj->setArrivage($link);
        }
        else if($link instanceof Litige) {
            $pj->setLitige($link);
        }

        return $pj;
    }

}
