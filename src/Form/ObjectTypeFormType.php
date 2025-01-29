<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Form;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObjectTypeFormType extends AbstractType
{
    public function __construct(private readonly ObjectMapping $objectMapping)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ($this->objectMapping->getObjectTypes() as $name) {
            $choices[$name] = $name;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
