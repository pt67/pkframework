<?php

declare(strict_types=1);

namespace JsonMapper\Tests\Unit\Middleware;

use JsonMapper\Cache\NullCache;
use JsonMapper\Enums\Visibility;
use JsonMapper\JsonMapperInterface;
use JsonMapper\Middleware\DocBlockAnnotations;
use JsonMapper\Tests\Implementation\ComplexObject;
use JsonMapper\Tests\Implementation\Models\User;
use JsonMapper\Tests\Implementation\SimpleObject;
use JsonMapper\ValueObjects\PropertyMap;
use JsonMapper\Wrapper\ObjectWrapper;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class DocBlockAnnotationsTest extends TestCase
{
    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testUpdatesThePropertyMap(): void
    {
        $middleware = new DocBlockAnnotations(new NullCache());
        $object = new ComplexObject();
        $propertyMap = new PropertyMap();
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), new ObjectWrapper($object), $propertyMap, $jsonMapper);

        self::assertTrue($propertyMap->hasProperty('child'));
        self::assertEquals('SimpleObject', $propertyMap->getProperty('child')->getType());
        self::assertEquals(Visibility::PRIVATE(), $propertyMap->getProperty('child')->getVisibility());
        self::assertTrue($propertyMap->getProperty('child')->isNullable());
        self::assertFalse($propertyMap->getProperty('child')->isArray());
        self::assertTrue($propertyMap->hasProperty('children'));
        self::assertEquals('SimpleObject', $propertyMap->getProperty('children')->getType());
        self::assertEquals(Visibility::PRIVATE(), $propertyMap->getProperty('children')->getVisibility());
        self::assertFalse($propertyMap->getProperty('children')->isNullable());
        self::assertTrue($propertyMap->getProperty('children')->isArray());
        self::assertTrue($propertyMap->hasProperty('user'));
        self::assertEquals('User', $propertyMap->getProperty('user')->getType());
        self::assertEquals(Visibility::PRIVATE(), $propertyMap->getProperty('user')->getVisibility());
        self::assertFalse($propertyMap->getProperty('user')->isNullable());
        self::assertFalse($propertyMap->getProperty('user')->isArray());
        self::assertTrue($propertyMap->hasProperty('mixedParam'));
        self::assertEquals('mixed', $propertyMap->getProperty('mixedParam')->getType());
        self::assertEquals('mixed', $propertyMap->getProperty('mixedParam')->getPropertyType()->getType());
        self::assertEquals(Visibility::PUBLIC(), $propertyMap->getProperty('mixedParam')->getVisibility());
        self::assertFalse($propertyMap->getProperty('mixedParam')->isNullable());
        self::assertFalse($propertyMap->getProperty('mixedParam')->isArray());
    }

    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testItCanHandleMissingDocBlock(): void
    {
        $middleware = new DocBlockAnnotations(new NullCache());
        $object = new class {
            public $number;
        };

        $propertyMap = new PropertyMap();
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), new ObjectWrapper($object), $propertyMap, $jsonMapper);

        self::assertEmpty($propertyMap->getIterator());
    }

    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testItCanHandleEmptyDocBlock(): void
    {
        $middleware = new DocBlockAnnotations(new NullCache());
        $object = new class {
            /** */
            public $number;
        };

        $propertyMap = new PropertyMap();
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), new ObjectWrapper($object), $propertyMap, $jsonMapper);

        self::assertEmpty($propertyMap->getIterator());
    }

    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testItCanHandleIncompleteDocBlock(): void
    {
        $middleware = new DocBlockAnnotations(new NullCache());
        $object = new class {
            /** @var */
            public $number;
        };

        $propertyMap = new PropertyMap();
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), new ObjectWrapper($object), $propertyMap, $jsonMapper);

        self::assertEmpty($propertyMap->getIterator());
    }

    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testReturnsFromCacheWhenAvailable(): void
    {
        $propertyMap = new PropertyMap();
        $objectWrapper = $this->createMock(ObjectWrapper::class);
        $objectWrapper->method('getName')->willReturn(__METHOD__);
        $objectWrapper->expects(self::never())->method('getReflectedObject');
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('has')->with(__METHOD__)->willReturn(true);
        $cache->method('get')->with(__METHOD__)->willReturn($propertyMap);
        $middleware = new DocBlockAnnotations($cache);
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), $objectWrapper, $propertyMap, $jsonMapper);
    }

    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testTypeIsCorrectlyCalculatedForNullableVars(): void
    {
        $middleware = new DocBlockAnnotations(new NullCache());
        $object = new class {
            /** @var NullableNumber|null This is a nullable number*/
            public $nullableNumber;
        };
        $propertyMap = new PropertyMap();
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), new ObjectWrapper($object), $propertyMap, $jsonMapper);

        self::assertEquals('NullableNumber', $propertyMap->getProperty('nullableNumber')->getType());
        self::assertTrue($propertyMap->getProperty('nullableNumber')->isNullable());
        self::assertFalse($propertyMap->getProperty('nullableNumber')->isArray());
    }

    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testTypeIsCorrectlyCalculatedForNullableArray(): void
    {
        $middleware = new DocBlockAnnotations(new NullCache());
        $object = new class {
            /** @var Number[]|null This is a nullable array number*/
            public $numbers;
        };
        $propertyMap = new PropertyMap();
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), new ObjectWrapper($object), $propertyMap, $jsonMapper);

        self::assertEquals('Number', $propertyMap->getProperty('numbers')->getType());
        self::assertTrue($propertyMap->getProperty('numbers')->isNullable());
        self::assertTrue($propertyMap->getProperty('numbers')->isArray());
    }

    /**
     * @covers \JsonMapper\Middleware\DocBlockAnnotations
     */
    public function testTypeIsCorrectlyCalculatedForNullableArrayWhenNullIsProvidedFirst(): void
    {
        $middleware = new DocBlockAnnotations(new NullCache());
        $object = new class {
            /** @var null|Number[] This is a nullable array number*/
            public $numbers;
        };
        $propertyMap = new PropertyMap();
        $jsonMapper = $this->createMock(JsonMapperInterface::class);

        $middleware->handle(new \stdClass(), new ObjectWrapper($object), $propertyMap, $jsonMapper);

        self::assertEquals('Number', $propertyMap->getProperty('numbers')->getType());
        self::assertTrue($propertyMap->getProperty('numbers')->isNullable());
        self::assertTrue($propertyMap->getProperty('numbers')->isArray());
    }
}
