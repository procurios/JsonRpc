<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Subject;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 *
 */
class Subject
{
    /** @var ReflectionClass */
    private $Subject;
    /** @var ReflectionClass */
    private $VisibilityClass;
    /** @var bool */
    private $isStatic;

    /**
     * @param string|object $subject Either an object or a class name
     * @param string $visibilityClass A parent class or interface of the given subject that will limit the availability of methods
     */
    public function __construct($subject, $visibilityClass = null)
    {
        $this->isStatic = !is_object($subject);

        if ($this->isStatic && !is_string($subject)) {
            throw new InvalidArgumentException('Subject must be either an object or a class name');
        }

        try {
            $this->Subject = new ReflectionClass($subject);
        } catch (ReflectionException $Exception) {
            throw new InvalidArgumentException($subject . ' is not a valid class name');
        }

        if (!is_null($visibilityClass)) {
            if (!is_string($visibilityClass)) {
                throw new InvalidArgumentException('Visibility class must be a class name');
            }

            try {
                $this->VisibilityClass = new ReflectionClass($visibilityClass);
            } catch (ReflectionException $Exception) {
                throw new InvalidArgumentException($visibilityClass . ' is not a valid class name');
            }

            if (!is_subclass_of($subject, $visibilityClass)) {
                throw new InvalidArgumentException('Visibility class must be a parent class or interface of the given subject');
            }
        }
    }

    /**
     * @param string $methodName
     * @return ReflectionMethod
     */
    public function getMethod($methodName)
    {
        if ($this->VisibilityClass && !$this->VisibilityClass->hasMethod($methodName)) {
            throw new InvalidArgumentException();
        }

        if (!$this->Subject->hasMethod($methodName)) {
            throw new InvalidArgumentException();
        }

        $Method = $this->Subject->getMethod($methodName);
        if (!$Method->isPublic()) {
            throw new InvalidArgumentException();
        }

        if (!$Method->isStatic() && $this->isStatic) {
            throw new InvalidArgumentException();
        }

        return $Method;
    }
}
