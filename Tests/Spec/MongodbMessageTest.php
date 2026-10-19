<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Mongodb\MongodbMessage;
use Interop\Queue\Spec\MessageSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('mongodb')]
class MongodbMessageTest extends MessageSpec
{
    protected function createMessage()
    {
        return new MongodbMessage();
    }
}
