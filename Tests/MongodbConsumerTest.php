<?php

namespace Enqueue\Mongodb\Tests;

use Enqueue\Mongodb\MongodbConsumer;
use Enqueue\Mongodb\MongodbContext;
use Enqueue\Mongodb\MongodbDestination;
use Enqueue\Mongodb\MongodbMessage;
use Enqueue\Mongodb\MongodbProducer;
use Enqueue\Test\ClassExtensionTrait;
use Interop\Queue\Consumer;
use Interop\Queue\Exception\InvalidMessageException;
use Interop\Queue\Message;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;

/**
 * @group mongodb
 */
class MongodbConsumerTest extends TestCase
{
    use ClassExtensionTrait;

    public function testShouldImplementConsumerInterface()
    {
        $this->assertClassImplements(Consumer::class, MongodbConsumer::class);
    }

    public function testShouldReturnInstanceOfDestination()
    {
        $destination = new MongodbDestination('queue');

        $consumer = new MongodbConsumer($this->createContextMock(), $destination);

        $this->assertSame($destination, $consumer->getQueue());
    }

    public function testCouldCallAcknowledgedMethod()
    {
        $message = new MongodbMessage();
        $message->setDeliveryId('theDeliveryId');

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock
            ->expects($this->once())
            ->method('deleteOne')
            ->with(['delivery_id' => 'theDeliveryId'])
        ;

        $context = $this->createContextMock();
        $context
            ->method('getCollection')
            ->willReturn($collectionMock)
        ;

        $consumer = new MongodbConsumer($context, new MongodbDestination('queue'));
        $consumer->acknowledge($message);
    }

    public function testCouldSetAndGetPollingInterval()
    {
        $destination = new MongodbDestination('queue');

        $consumer = new MongodbConsumer($this->createContextMock(), $destination);
        $consumer->setPollingInterval(123456);

        $this->assertEquals(123456, $consumer->getPollingInterval());
    }

    public function testRedeliveryDelayDefaultsTo1200()
    {
        $consumer = new MongodbConsumer($this->createContextMock(), new MongodbDestination('queue'));

        $this->assertEquals(1200, $consumer->getRedeliveryDelay());
    }

    public function testCouldSetAndGetRedeliveryDelay()
    {
        $consumer = new MongodbConsumer($this->createContextMock(), new MongodbDestination('queue'));
        $consumer->setRedeliveryDelay(7200);

        $this->assertEquals(7200, $consumer->getRedeliveryDelay());
    }

    public function testRejectShouldThrowIfInstanceOfMessageIsInvalid()
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage(
            'The message must be an instance of '.
            'Enqueue\Mongodb\MongodbMessage '.
            'but it is Enqueue\Mongodb\Tests\InvalidMessage.'
        );

        $consumer = new MongodbConsumer($this->createContextMock(), new MongodbDestination('queue'));
        $consumer->reject(new InvalidMessage());
    }

    public function testShouldDoNothingOnReject()
    {
        $queue = new MongodbDestination('queue');

        $message = new MongodbMessage();
        $message->setBody('theBody');
        $message->setDeliveryId('theDeliveryId');

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock
            ->expects($this->once())
            ->method('deleteOne')
            ->with(['delivery_id' => 'theDeliveryId'])
        ;

        $context = $this->createContextMock();
        $context
            ->expects($this->never())
            ->method('createProducer')
        ;
        $context
            ->method('getCollection')
            ->willReturn($collectionMock)
        ;

        $consumer = new MongodbConsumer($context, $queue);

        // Reject without requeue deletes the claim and sends nothing.
        $consumer->reject($message);
    }

    public function testRejectShouldReSendMessageToSameQueueOnRequeue()
    {
        $queue = new MongodbDestination('queue');

        $message = new MongodbMessage();
        $message->setBody('theBody');
        $message->setDeliveryId('theDeliveryId');

        $producerMock = $this->createProducerMock();
        $producerMock
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->identicalTo($queue),
                $this->callback(function (MongodbMessage $sent) use ($message): bool {
                    // A copy is resent: same body, marked redelivered, claim released.
                    return $sent !== $message
                        && $sent->getBody() === 'theBody'
                        && $sent->isRedelivered()
                        && $sent->getDeliveryId() === null;
                })
            )
        ;

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock
            ->expects($this->once())
            ->method('deleteOne')
            ->with(['delivery_id' => 'theDeliveryId'])
        ;

        $context = $this->createContextMock();
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producerMock)
        ;
        $context
            ->method('getCollection')
            ->willReturn($collectionMock)
        ;

        $consumer = new MongodbConsumer($context, $queue);

        $consumer->reject($message, true);
    }

    /**
     * @return MongodbProducer|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createProducerMock()
    {
        return $this->createMock(MongodbProducer::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|MongodbContext
     */
    private function createContextMock()
    {
        return $this->createMock(MongodbContext::class);
    }
}

class InvalidMessage implements Message
{
    public function getBody(): string
    {
        throw new \BadMethodCallException('This should not be called directly');
    }

    public function setBody(string $body): void
    {
    }

    public function setProperties(array $properties): void
    {
    }

    public function getProperties(): array
    {
        throw new \BadMethodCallException('This should not be called directly');
    }

    public function setProperty(string $name, $value): void
    {
    }

    public function getProperty(string $name, $default = null)
    {
    }

    public function setHeaders(array $headers): void
    {
    }

    public function getHeaders(): array
    {
        throw new \BadMethodCallException('This should not be called directly');
    }

    public function setHeader(string $name, $value): void
    {
    }

    public function getHeader(string $name, $default = null)
    {
    }

    public function setRedelivered(bool $redelivered): void
    {
    }

    public function isRedelivered(): bool
    {
        throw new \BadMethodCallException('This should not be called directly');
    }

    public function setCorrelationId(?string $correlationId = null): void
    {
    }

    public function getCorrelationId(): ?string
    {
        throw new \BadMethodCallException('This should not be called directly');
    }

    public function setMessageId(?string $messageId = null): void
    {
    }

    public function getMessageId(): ?string
    {
        throw new \BadMethodCallException('This should not be called directly');
    }

    public function getTimestamp(): ?int
    {
        throw new \BadMethodCallException('This should not be called directly');
    }

    public function setTimestamp(?int $timestamp = null): void
    {
    }

    public function setReplyTo(?string $replyTo = null): void
    {
    }

    public function getReplyTo(): ?string
    {
        throw new \BadMethodCallException('This should not be called directly');
    }
}
