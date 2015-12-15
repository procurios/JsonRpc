<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Subject;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Procurios\Json\JsonRpc\Subject\Subject;
use Procurios\Json\JsonRpc\test\assets\MockSubjectClass;
use Procurios\Json\JsonRpc\test\assets\MockSubjectInterface;
use Procurios\Json\JsonRpc\test\assets\MockSubjectParent;
use Procurios\Json\JsonRpc\test\assets\OtherInterface;
use ReflectionMethod;

/**
 *
 */
class SubjectTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getValidSubjects
     * @param mixed $subject
     */
    public function testValidSubjects($subject)
    {
        $this->assertInstanceOf(Subject::class, new Subject($subject));
    }

    /**
     * @return array
     */
    public function getValidSubjects()
    {
        return [
            'object' => [new MockSubjectClass()],
            'class name' => [MockSubjectClass::class],
        ];
    }

    /**
     * @dataProvider getInvalidSubjects
     * @param mixed $subject
     */
    public function testInvalidSubjects($subject)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new Subject($subject);
    }

    /**
     * @return array
     */
    public function getInvalidSubjects()
    {
        return [
            'null' => [null],
            'array' => [[]],
            'string that is not a class' => ['foo bar'],
        ];
    }

    /**
     * @dataProvider getValidVisibilityClasses
     * @param mixed $visibilityClass
     */
    public function testValidVisibilityClasses($visibilityClass)
    {
        $this->assertInstanceOf(Subject::class, new Subject(MockSubjectClass::class, $visibilityClass));
    }

    /**
     * @return array
     */
    public function getValidVisibilityClasses()
    {
        return [
            'interface' => [MockSubjectInterface::class],
            'parent class' => [MockSubjectParent::class],
        ];
    }

    /**
     * @dataProvider getInvalidVisibilityClasses
     * @param mixed $visibilityClass
     */
    public function testInvalidVisibilityClasses($visibilityClass)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new Subject(MockSubjectClass::class, $visibilityClass);
    }

    /**
     * @return array
     */
    public function getInvalidVisibilityClasses()
    {
        return [
            'array' => [[]],
            'string that is not a class' => ['foo bar'],
            'other interface' => [OtherInterface::class],
            'not parent class' => [self::class],
        ];
    }

    public function testGetMethod()
    {
        $Subject = new Subject(MockSubjectClass::class);
        $this->assertInstanceOf(ReflectionMethod::class, $Subject->getMethod('bar'));
    }

    public function testGetNotExistingMethod()
    {
        $Subject = new Subject(MockSubjectClass::class);
        $this->setExpectedException(InvalidArgumentException::class);
        $Subject->getMethod('oof');
    }

    public function testGetNonStaticMethodWithStaticSubject()
    {
        $Subject = new Subject(MockSubjectClass::class);
        $this->setExpectedException(InvalidArgumentException::class);
        $Subject->getMethod('foo');
    }

    public function testGetMethodNotDefinedInVisibilityClass()
    {
        $Subject = new Subject(MockSubjectClass::class, MockSubjectInterface::class);
        $this->setExpectedException(InvalidArgumentException::class);
        $Subject->getMethod('bar');
    }

    public function testProtectedMethod()
    {
        $Subject = new Subject(MockSubjectClass::class);
        $this->setExpectedException(InvalidArgumentException::class);
        $Subject->getMethod('quux');
    }

    public function testPrivateMethod()
    {
        $Subject = new Subject(MockSubjectClass::class);
        $this->setExpectedException(InvalidArgumentException::class);
        $Subject->getMethod('quuux');
    }
}
