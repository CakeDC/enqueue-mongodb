<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Test\MongodbExtensionTrait;
use Interop\Queue\Spec\SendAndReceiveTimeToLiveMessagesFromQueueSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
#[Group('mongodb')]
class MongodbSendAndReceiveTimeToLiveMessagesFromQueueTest extends SendAndReceiveTimeToLiveMessagesFromQueueSpec
{
    use MongodbExtensionTrait;

    protected function createContext()
    {
        return $this->buildMongodbContext();
    }
}
