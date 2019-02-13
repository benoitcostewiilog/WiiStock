<?php
namespace App\Form;
use App\Entity\Receptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
class ReceptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('numeroReception')
            ->add('fournisseur')
            ->add('utilisateur')
            ->add('statut')
            ->add('date')
            ->add('dateAttendu')
            ->add('commentaire')
            ;
    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Receptions::class,
        ]);
    }
}