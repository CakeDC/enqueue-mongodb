<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Mongodb\MongodbDestination;
use Interop\Queue\Spec\QueueSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('mongodb')]
class MongodbQueueTest extends QueueSpec
{
    protected function createQueue()
    {
        return new MongodbDestination(self::EXPECTED_QUEUE_NAME);
    }
}
