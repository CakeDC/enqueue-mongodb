<?php

declare(strict_types=1);

namespace Enqueue\Mongodb;

use Interop\Queue\Consumer;
use Interop\Queue\Exception\InvalidMessageException;
use Interop\Queue\Message;
use Interop\Queue\Queue;

class MongodbConsumer implements Consumer
{
    use MongodbConsumerHelperTrait;

    /**
     * @var MongodbContext
     */
    private $context;

    /**
     * @var MongodbDestination
     */
    private $queue;

    /**
     * @var int microseconds
     */
    private $pollingInterval;

    /**
     * Seconds after which an un-acked claim becomes available again.
     *
     * @var int
     */
    private $redeliveryDelay = 1200;

    public function __construct(MongodbContext $context, MongodbDestination $queue)
    {
        $this->context = $context;
        $this->queue = $queue;

        $this->pollingInterval = 1000;
    }

    /**
     * Set polling interval in milliseconds.
     */
    public function setPollingInterval(int $msec): void
    {
        $this->pollingInterval = $msec;
    }

    /**
     * Get polling interval in milliseconds.
     */
    public function getPollingInterval(): int
    {
        return $this->pollingInterval;
    }

    /**
     * @return MongodbDestination
     */
    public function getQueue(): Queue
    {
        return $this->queue;
    }

    /**
     * @return MongodbMessage
     */
    public function receive(int $timeout = 0): ?Message
    {
        $timeout /= 1000;
        $startAt = microtime(true);

        while (true) {
            $message = $this->receiveMessage();

            if ($message) {
                return $message;
            }

            if ($timeout && (microtime(true) - $startAt) >= $timeout) {
                return null;
            }

            usleep($this->pollingInterval * 1000);

            if ($timeout && (microtime(true) - $startAt) >= $timeout) {
                return null;
            }
        }
    }

    /**
     * @return MongodbMessage
     */
    public function receiveNoWait(): ?Message
    {
        return $this->receiveMessage();
    }

    /**
     * @param MongodbMessage $message
     */
    public function acknowledge(Message $message): void
    {
        InvalidMessageException::assertMessageInstanceOf($message, MongodbMessage::class);

        $this->deleteMessage((string) $message->getDeliveryId());
    }

    /**
     * @param MongodbMessage $message
     */
    public function reject(Message $message, bool $requeue = false): void
    {
        InvalidMessageException::assertMessageInstanceOf($message, MongodbMessage::class);

        $deliveryId = (string) $message->getDeliveryId();
        if ($requeue) {
            $message = clone $message;
            $message->setRedelivered(true);
            $message->setDeliveryId(null);
            $this->context->createProducer()->send($this->queue, $message);
        }

        $this->deleteMessage($deliveryId);
    }

    protected function getContext(): MongodbContext
    {
        return $this->context;
    }

    private function receiveMessage(): ?MongodbMessage
    {
        $document = $this->claimMessage([$this->queue->getName()], $this->redeliveryDelay);
        if (!$document) {
            return null;
        }

        return $this->context->convertMessage($document);
    }
}
