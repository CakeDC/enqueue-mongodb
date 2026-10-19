<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Test\MongodbExtensionTrait;
use Interop\Queue\Spec\ContextSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
#[Group('mongodb')]
class MongodbContextTest extends ContextSpec
{
    use MongodbExtensionTrait;

    protected function createContext()
    {
        return $this->buildMongodbContext();
    }
}
