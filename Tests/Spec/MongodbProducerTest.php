<?php

namespace Enqueue\Mongodb\Tests\Spec;

use Enqueue\Test\MongodbExtensionTrait;
use Interop\Queue\Spec\ProducerSpec;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
#[Group('mongodb')]
class MongodbProducerTest extends ProducerSpec
{
    use MongodbExtensionTrait;

    protected function createProducer()
    {
        return $this->buildMongodbContext()->createProducer();
    }
}
