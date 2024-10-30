<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('slug')
            ->add('image', FileType::class, [
                'label' => 'Image (Image file)',
                'mapped' => false,
                'required' => false, //Champ pas obligatoire
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ],
                        'maxSizeMessage' => 'Votre image ne doit pas dépasser les 1024K',
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier image valide (JPEG, PNG, GIF)',
                    ])
                ],
            ])
            ->add('description')
            ->add('parent', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Aucune',
                'label' => 'Catégorie parent'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
