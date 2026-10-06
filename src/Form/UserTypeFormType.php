<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Form;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserTypeFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [];
        foreach (AccessControlEntry::USER_TYPES as $name => $code) {
            $choices[$name] = $code;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}
