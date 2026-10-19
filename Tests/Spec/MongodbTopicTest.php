<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Mongodb\MongodbDestination;
use Interop\Queue\Spec\TopicSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('mongodb')]
class MongodbTopicTest extends TopicSpec
{
    protected function createTopic()
    {
        return new MongodbDestination(self::EXPECTED_TOPIC_NAME);
    }
}
