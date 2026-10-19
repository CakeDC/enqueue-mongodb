<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Test\MongodbExtensionTrait;
use Interop\Queue\Spec\SendToAndReceiveNoWaitFromTopicSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
#[Group('mongodb')]
class MongodbSendToAndReceiveNoWaitFromTopicTest extends SendToAndReceiveNoWaitFromTopicSpec
{
    use MongodbExtensionTrait;

    protected function createContext()
    {
        return $this->buildMongodbContext();
    }
}
