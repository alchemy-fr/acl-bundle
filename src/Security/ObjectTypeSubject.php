<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

/**
 * The subject voted on for an ACE with no object id, which applies to every object
 * of the type: there is no object to vote on.
 */
final readonly class ObjectTypeSubject
{
    public function __construct(
        public string $objectType,
        /** @var class-string */
        public string $className,
    ) {
    }
}
