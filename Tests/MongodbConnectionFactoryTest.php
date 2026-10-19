<?php

namespace Enqueue\Mongodb\Tests;

use Enqueue\Mongodb\MongodbConnectionFactory;
use Enqueue\Mongodb\MongodbContext;
use Enqueue\Test\ClassExtensionTrait;
use Enqueue\Test\ReadAttributeTrait;
use Interop\Queue\ConnectionFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('mongodb')]
class MongodbConnectionFactoryTest extends TestCase
{
    use ClassExtensionTrait;
    use ReadAttributeTrait;

    public function testShouldImplementConnectionFactoryInterface()
    {
        $this->assertClassImplements(ConnectionFactory::class, MongodbConnectionFactory::class);
    }

    public function testCouldBeConstructedWithEmptyConfiguration()
    {
        $params = [
            'dsn' => 'mongodb://127.0.0.1/',
            'dbname' => 'enqueue',
            'collection_name' => 'enqueue',
        ];

        $factory = new MongodbConnectionFactory();
        $this->assertAttributeEquals($params, 'config', $factory);
    }

    public function testCouldBeConstructedWithCustomConfiguration()
    {
        $params = [
            'dsn' => 'mongodb://127.0.0.3/',
            'dbname' => 'enqueue',
            'collection_name' => 'enqueue',
        ];

        $factory = new MongodbConnectionFactory($params);

        $this->assertAttributeEquals($params, 'config', $factory);
    }

    public function testCouldBeConstructedWithCustomConfigurationFromDsn()
    {
        $params = [
            'dsn' => 'mongodb://127.0.0.3/test-db-name?enqueue_collection=collection-name&polling_interval=3000&redelivery_delay=7200',
            'dbname' => 'test-db-name',
            'collection_name' => 'collection-name',
            'polling_interval' => 3000,
            'redelivery_delay' => 7200,
        ];

        $factory = new MongodbConnectionFactory($params['dsn']);

        $this->assertAttributeEquals($params, 'config', $factory);
    }

    public function testCollectionNameDsnAlias()
    {
        $factory = new MongodbConnectionFactory('mongodb://127.0.0.3/test-db-name?collection_name=collection-name');

        $config = $factory->createContext()->getConfig();

        $this->assertSame('collection-name', $config['collection_name']);
    }

    public function testEnqueueCollectionDsnParamWinsOverCollectionNameAlias()
    {
        $factory = new MongodbConnectionFactory('mongodb://127.0.0.3/test-db-name?enqueue_collection=first&collection_name=second');

        $config = $factory->createContext()->getConfig();

        $this->assertSame('first', $config['collection_name']);
    }

    public function testShouldCreateContext()
    {
        $factory = new MongodbConnectionFactory();

        $context = $factory->createContext();

        $this->assertInstanceOf(MongodbContext::class, $context);
    }
}
