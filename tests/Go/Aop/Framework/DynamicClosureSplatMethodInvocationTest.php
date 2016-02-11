<?php

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\MethodInvocation;
use Go\Stubs\First;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2012-12-20 at 11:58:54.
 */
class DynamicClosureSplatMethodInvocationTest extends \PHPUnit_Framework_TestCase
{

    const FIRST_CLASS_NAME = First::class;

    protected static $invocationClass;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        // Add version check to prevent fatals
        if (PHP_VERSION_ID >= 50600) {
            self::$invocationClass = MethodInvocationComposer::compose(false, true, true, false);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped("Closure Method Invocation with splat works only on PHP 5.6 and greater");
        }
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("Skipped due to the bug https://github.com/facebook/hhvm/issues/1203");
        }
    }

    /**
     * Tests dynamic method invocations
     *
     * @dataProvider dynamicMethodsBatch
     */
    public function testDynamicMethodInvocation($methodName, $expectedResult)
    {
        $child      = $this->getMock(self::FIRST_CLASS_NAME, array('none'));
        $invocation = new self::$invocationClass(self::FIRST_CLASS_NAME, $methodName, []);

        $result = $invocation($child);
        $this->assertEquals($expectedResult, $result);
    }

    public function testValueChangedByReference()
    {
        $child      = $this->getMock(self::FIRST_CLASS_NAME, array('none'));
        $invocation = new self::$invocationClass(self::FIRST_CLASS_NAME, 'passByReference', []);

        $value  = 'test';
        $result = $invocation($child, array(&$value));
        $this->assertEquals(null, $result);
        $this->assertEquals(null, $value);
    }

    public function testInvocationWithDynamicArguments()
    {
        $child      = $this->getMock(self::FIRST_CLASS_NAME, array('none'));
        $invocation = new self::$invocationClass(self::FIRST_CLASS_NAME, 'variableArgsTest', []);

        $args     = [];
        $expected = '';
        for ($i=0; $i<10; $i++) {
            $args[]   = $i;
            $expected .= $i;
            $result   = $invocation($child, $args);
            $this->assertEquals($expected, $result);
        }
    }

    public function testRecursionWorks()
    {
        $child      = $this->getMock(self::FIRST_CLASS_NAME, array('recursion'));
        $invocation = new self::$invocationClass(self::FIRST_CLASS_NAME, 'recursion', []);

        $child->expects($this->exactly(5))->method('recursion')->will($this->returnCallback(
            function ($value, $level) use ($child, $invocation) {
                return $invocation($child, array($value, $level));
            }
        ));

        $this->assertEquals(5, $child->recursion(5,0));
        $this->assertEquals(20, $child->recursion(5,3));
    }

    public function testInterceptorIsCalledForInvocation()
    {
        $child  = $this->getMock(self::FIRST_CLASS_NAME, array('none'));
        $value  = 'test';
        $advice = $this->getMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->will($this->returnCallback(function (MethodInvocation $object) use (&$value) {
                $value = 'ok';
                return $object->proceed();
            }));

        $invocation = new self::$invocationClass(self::FIRST_CLASS_NAME, 'publicMethod', array($advice));

        $result = $invocation($child, []);
        $this->assertEquals('ok', $value);
        $this->assertEquals(T_PUBLIC, $result);
    }

    public function dynamicMethodsBatch()
    {
        return array(
            array('publicMethod', T_PUBLIC),
            array('protectedMethod', T_PROTECTED),
            // array('privateMethod', T_PRIVATE), This will throw an ReflectionException, need to add use case for that
        );
    }
}
