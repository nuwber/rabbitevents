<?php

namespace Nuwber\Events\Tests;

use Nuwber\Events\NameResolver;
use PHPUnit\Framework\TestCase;

class NameResolverTest extends TestCase
{
    private $event = 'item.created';

    private $serviceName = 'test-app';

    /** @var NameResolver */
    private $resolver;

    public function setUp()
    {
        $this->resolver = new NameResolver($this->event, $this->serviceName);
    }

    public function testQueue()
    {
        $this->assertEquals("{$this->serviceName}:{$this->event}", $this->resolver->queue());
    }

    public function testBind()
    {
        $this->assertEquals($this->event, $this->resolver->bind());
    }
}
