<?php

namespace App\Form;

use App\Entity\Transferts;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class TransfertsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('statut')
            ->add('quantite')
            ->add('date_transfert')
            ->add('emplacement_debut', EntityType::class, array(
                'class' => 'App\Entity\Quais',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('emplacement_fin', EntityType::class, array(
                'class' => 'App\Entity\Quais',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('zone_debut', EntityType::class, array(
                'class' => 'App\Entity\Zones',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('zone_fin', EntityType::class, array(
                'class' => 'App\Entity\Zones',
                'choice_label' => 'nom',
                'multiple' => false,
                ))
            ->add('article')
            ->add('historique')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Transferts::class,
        ]);
    }
}
