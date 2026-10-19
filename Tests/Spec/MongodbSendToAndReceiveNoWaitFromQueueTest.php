<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Test\MongodbExtensionTrait;
use Interop\Queue\Spec\SendToAndReceiveNoWaitFromQueueSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
#[Group('mongodb')]
class MongodbSendToAndReceiveNoWaitFromQueueTest extends SendToAndReceiveNoWaitFromQueueSpec
{
    use MongodbExtensionTrait;

    protected function createContext()
    {
        return $this->buildMongodbContext();
    }
}
