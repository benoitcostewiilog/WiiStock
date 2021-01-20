<?php

namespace App\Repository\IOT;

use App\Entity\Dashboard;
use App\Entity\IOT\Message;
use Doctrine\ORM\EntityRepository;

/**
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends EntityRepository
{
}
