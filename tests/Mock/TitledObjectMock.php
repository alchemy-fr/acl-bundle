<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests\Mock;

class TitledObjectMock extends ObjectMock implements \Stringable
{
    public function __toString(): string
    {
        return 'Publication #'.$this->getId();
    }
}
