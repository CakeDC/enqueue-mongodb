<?php

namespace Enqueue\Mongodb\Tests\Functional;

use Enqueue\Mongodb\MongodbContext;
use Enqueue\Mongodb\MongodbMessage;
use Enqueue\Test\MongodbExtensionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group functional
 */
class MongodbConsumerTest extends TestCase
{
    use MongodbExtensionTrait;

    /**
     * @var MongodbContext
     */
    private $context;

    protected function setUp(): void
    {
        $this->context = $this->buildMongodbContext();
    }

    protected function tearDown(): void
    {
        if ($this->context) {
            $this->context->close();
        }

        parent::tearDown();
    }

    public function testShouldSetPublishedAtDateToReceivedMessage()
    {
        $context = $this->context;
        $queue = $context->createQueue(__METHOD__);

        $consumer = $context->createConsumer($queue);

        // guard
        $this->assertNull($consumer->receiveNoWait());

        $time = (int) (microtime(true) * 10000);

        $expectedBody = __CLASS__.$time;

        $producer = $context->createProducer();

        $message = $context->createMessage($expectedBody);
        $message->setPublishedAt($time);
        $producer->send($queue, $message);

        $message = $consumer->receive(8000); // 8 sec

        $this->assertInstanceOf(MongodbMessage::class, $message);
        $consumer->acknowledge($message);
        $this->assertSame($expectedBody, $message->getBody());
        $this->assertSame($time, $message->getPublishedAt());
    }

    public function testClaimAndAcknowledgeDeletesClaimedDocument()
    {
        $context = $this->context;
        $queue = $context->createQueue(__METHOD__);

        $consumer = $context->createConsumer($queue);

        // guard
        $this->assertNull($consumer->receiveNoWait());

        $producer = $context->createProducer();
        $producer->send($queue, $context->createMessage(__CLASS__.'_claim_ack'));

        $message = $consumer->receiveNoWait();

        $this->assertInstanceOf(MongodbMessage::class, $message);
        // The document is claimed, not deleted: a delivery id is assigned.
        $this->assertNotEmpty($message->getDeliveryId());

        // A second pop sees nothing while the claim is held.
        $this->assertNull($consumer->receiveNoWait());

        $consumer->acknowledge($message);

        $this->assertSame(0, $context->getCollection()->countDocuments(['queue' => $queue->getQueueName()]));
        $this->assertNull($consumer->receiveNoWait());
    }

    public function testExpiredClaimIsRedeliveredToSubscriptionConsumer()
    {
        $context = $this->context;
        $queue = $context->createQueue(__METHOD__);

        // A claim whose worker died: delivery id set, redeliver time in the past.
        $context->getCollection()->insertOne([
            'published_at' => (int) (microtime(true) * 10000),
            'body' => __CLASS__.'_expired_claim',
            'headers' => [],
            'properties' => [],
            'priority' => null,
            'queue' => $queue->getQueueName(),
            'redelivered' => false,
            'delivery_id' => 'dead-worker-claim',
            'redeliver_after' => time() - 10,
            'delayed_until' => 0,
        ]);

        $consumer = $context->createConsumer($queue);

        // Guard: a plain pop never sees a held claim.
        $this->assertNull($consumer->receiveNoWait());

        $received = [];
        $subscriptionConsumer = $context->createSubscriptionConsumer();
        $subscriptionConsumer->subscribe($consumer, function (MongodbMessage $message) use (&$received, $consumer): bool {
            $received[] = $message->getBody();
            $consumer->acknowledge($message);

            return false;
        });
        $subscriptionConsumer->consume(8000);

        $this->assertSame([__CLASS__.'_expired_claim'], $received);
    }

    public function testShouldOrderMessagesWithSamePriorityByPublishedAtDate()
    {
        $context = $this->context;
        $queue = $context->createQueue(__METHOD__);

        $consumer = $context->createConsumer($queue);

        // guard
        $this->assertNull($consumer->receiveNoWait());

        $time = (int) (microtime(true) * 10000);
        $olderTime = $time - 10000;

        $expectedPriority5Body = __CLASS__.'_priority5_'.$time;
        $expectedPriority5BodyOlderTime = __CLASS__.'_priority5Old_'.$olderTime;

        $producer = $context->createProducer();

        $message = $context->createMessage($expectedPriority5Body);
        $message->setPriority(5);
        $message->setPublishedAt($time);
        $producer->send($queue, $message);

        $message = $context->createMessage($expectedPriority5BodyOlderTime);
        $message->setPriority(5);
        $message->setPublishedAt($olderTime);
        $producer->send($queue, $message);

        $message = $consumer->receive(8000); // 8 sec

        $this->assertInstanceOf(MongodbMessage::class, $message);
        $consumer->acknowledge($message);
        $this->assertSame($expectedPriority5BodyOlderTime, $message->getBody());

        $message = $consumer->receive(8000); // 8 sec

        $this->assertInstanceOf(MongodbMessage::class, $message);
        $consumer->acknowledge($message);
        $this->assertSame($expectedPriority5Body, $message->getBody());
    }
}
