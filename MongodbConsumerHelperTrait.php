<?php

declare(strict_types=1);

namespace Enqueue\Mongodb;

/**
 * Claim-based consumption helper, ported from the DBAL transport
 * (DbalConsumerHelperTrait) and CakeDC cakephp-enqueue (CakeConsumerHelperTrait).
 *
 * Instead of deleting a document on pop (at-most-once: a crash between pop
 * and finish loses the job), a worker atomically claims it via `delivery_id`.
 * A crashed claim expires through `redeliver_after` and becomes available
 * again. `acknowledge()` deletes by `delivery_id`, so a slow worker can never
 * delete a document already re-claimed by someone else.
 */
trait MongodbConsumerHelperTrait
{
    /**
     * @var float|null
     */
    private $redeliverMessagesLastExecutedAt = null;

    /**
     * @var float|null
     */
    private $removeExpiredMessagesLastExecutedAt = null;

    /**
     * @return \Enqueue\Mongodb\MongodbContext
     */
    abstract protected function getContext(): MongodbContext;

    /**
     * Atomically claim one ready document of the given queues.
     *
     * @param array<string> $queueNames Transport queue names to pick from
     * @param int $redeliveryDelay Seconds after which an un-acked claim becomes available again
     * @return array|null The claimed document, null when nothing is ready
     */
    protected function claimMessage(array $queueNames, int $redeliveryDelay): ?array
    {
        if (empty($queueNames)) {
            return null;
        }

        $now = time();
        $deliveryId = bin2hex(random_bytes(16));

        $document = $this->getContext()->getCollection()->findOneAndUpdate(
            [
                'queue' => ['$in' => array_values($queueNames)],
                'delayed_until' => ['$lte' => $now],
                'delivery_id' => null,
            ],
            [
                '$set' => [
                    'delivery_id' => $deliveryId,
                    'redeliver_after' => $now + $redeliveryDelay,
                ],
            ],
            [
                'sort' => ['priority' => -1, 'published_at' => 1],
                'typeMap' => ['root' => 'array', 'document' => 'array'],
                'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ]
        );

        if (!$document) {
            return null;
        }

        if (
            !empty($document['time_to_live'])
            && empty($document['redelivered'])
            && $document['time_to_live'] <= $now
        ) {
            $this->deleteMessage($deliveryId);

            return null;
        }

        return $document;
    }

    /**
     * Delete a claimed document by its delivery id.
     */
    protected function deleteMessage(string $deliveryId): void
    {
        if ($deliveryId === '') {
            return;
        }

        $this->getContext()->getCollection()->deleteOne([
            'delivery_id' => $deliveryId,
        ]);
    }

    /**
     * Release expired claims back to ready state.
     */
    protected function redeliverMessages(): void
    {
        if ($this->redeliverMessagesLastExecutedAt === null) {
            $this->redeliverMessagesLastExecutedAt = microtime(true);
        } elseif (microtime(true) - $this->redeliverMessagesLastExecutedAt < 1) {
            return;
        }

        try {
            $this->getContext()->getCollection()->updateMany(
                [
                    'redeliver_after' => ['$lt' => time()],
                    'delivery_id' => ['$ne' => null],
                ],
                [
                    '$set' => [
                        'delivery_id' => null,
                        'redelivered' => true,
                    ],
                ]
            );

            $this->redeliverMessagesLastExecutedAt = microtime(true);
        } catch (\Exception $e) {
            // maybe next time we'll get more luck
        }
    }

    /**
     * Delete expired unclaimed messages.
     */
    protected function removeExpiredMessages(): void
    {
        if ($this->removeExpiredMessagesLastExecutedAt === null) {
            $this->removeExpiredMessagesLastExecutedAt = microtime(true);
        } elseif (microtime(true) - $this->removeExpiredMessagesLastExecutedAt < 1) {
            return;
        }

        try {
            $this->getContext()->getCollection()->deleteMany([
                'time_to_live' => ['$ne' => null, '$lte' => time()],
                'redelivered' => false,
                'delivery_id' => null,
            ]);

            $this->removeExpiredMessagesLastExecutedAt = microtime(true);
        } catch (\Exception $e) {
            // maybe next time we'll get more luck
        }
    }
}
