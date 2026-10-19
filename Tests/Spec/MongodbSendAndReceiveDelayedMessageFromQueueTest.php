<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Test\MongodbExtensionTrait;
use Interop\Queue\Spec\SendAndReceiveDelayedMessageFromQueueSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
#[Group('mongodb')]
class MongodbSendAndReceiveDelayedMessageFromQueueTest extends SendAndReceiveDelayedMessageFromQueueSpec
{
    use MongodbExtensionTrait;

    protected function createContext()
    {
        return $this->buildMongodbContext();
    }
}
