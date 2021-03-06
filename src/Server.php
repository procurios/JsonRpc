<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc;

use InvalidArgumentException;
use Procurios\Json\JsonRpc\exception\CouldNotParse;
use Procurios\Json\JsonRpc\exception\InvalidParameter;
use Procurios\Json\JsonRpc\exception\JsonRpcError;
use Procurios\Json\JsonRpc\exception\MethodNotFound;
use Procurios\Json\JsonRpc\exception\MissingParameter;
use Procurios\Json\JsonRpc\exception\TooManyParameters;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;
use Procurios\Json\JsonRpc\Response\BatchResponse;
use Procurios\Json\JsonRpc\Response\EmptyResponse;
use Procurios\Json\JsonRpc\Response\ErrorResponse;
use Procurios\Json\JsonRpc\Response\Response;
use Procurios\Json\JsonRpc\Response\SuccessResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * JSON-RPC 2.0 server
 */
class Server
{
    /** @var null|object The subject object or null if the subject is a class */
    private $subject = null;
    /** @var ReflectionClass */
    private $SubjectClass;
    /** @var ReflectionClass */
    private $VisibilityClass;
    /** @var bool */
    private $isStatic;

    /**
     * @param string|object $subject Either an object or a class name to expose
     * @param string $visibilityClass A parent class or interface to which the exposed methods will be limited
     */
    public function __construct($subject, $visibilityClass = null)
    {
        $this->isStatic = !is_object($subject);

        if (!$this->isStatic) {
            $this->subject = $subject;
        } elseif (!is_string($subject)) {
            throw new InvalidArgumentException('Subject must either be an object or a valid class name');
        }

        try {
            $this->SubjectClass = new ReflectionClass($subject);
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
     * @param Request $Request
     * @return Response
     */
    public function handleRequest(Request $Request)
    {
        if (is_null($Request->getId())) {
            return $this->handleNotification($Request);
        }

        try {
            $result = $this->invokeRequest($Request);

            return new SuccessResponse($Request->getId(), $result);
        } catch (MethodNotFound $Exception) {
            return ErrorResponse::methodNotFound($Request->getId());
        } catch (TooManyParameters $Exception) {
            return ErrorResponse::invalidParameters($Request->getId(), 'Too many parameters');
        } catch (InvalidParameter $Exception) {
            return ErrorResponse::invalidParameters($Request->getId(), $Exception->getMessage() ?: null);
        } catch (MissingParameter $Exception) {
            return ErrorResponse::invalidParameters($Request->getId(), $Exception->getMessage() ?: null);
        }
    }

    /**
     * @param Request $Request
     * @return EmptyResponse
     */
    private function handleNotification(Request $Request)
    {
        try {
            $this->invokeRequest($Request);
        } catch (JsonRpcError $Exception) {
            // Do not report errors on notifications
        }

        return new EmptyResponse();
    }

    /**
     * @param BatchRequest $Request
     * @return BatchResponse
     */
    public function handleBatchRequest(BatchRequest $Request)
    {
        $responses = [];
        foreach ($Request->getRequests() as $Request) {
            $responses[] = $this->handleRequest($Request);
        }
        return new BatchResponse($responses);
    }

    /**
     * Convenience method to handle a ServerRequestInterface object directly
     * @param ServerRequestInterface $HttpRequest
     * @param ResponseInterface $TargetHttpResponse
     * @return ResponseInterface
     */
    public function handleServerRequest(ServerRequestInterface $HttpRequest, ResponseInterface $TargetHttpResponse)
    {
        try {
            $Request = Request::fromHttpRequest($HttpRequest);
        } catch (CouldNotParse $Exception) {
            return $this->httpResponseFromResponse(ErrorResponse::parseError(), $TargetHttpResponse);
        } catch (InvalidArgumentException $Exception) {
            return $this->httpResponseFromResponse(ErrorResponse::invalidRequest($Exception->getMessage()), $TargetHttpResponse);
        }

        if ($Request instanceof BatchRequest) {
            return $this->httpResponseFromResponse($this->handleBatchRequest($Request), $TargetHttpResponse);
        }

        return $this->httpResponseFromResponse($this->handleRequest($Request), $TargetHttpResponse);
    }

    /**
     * @param Response $Response
     * @param ResponseInterface $TargetHttpResponse
     * @return ResponseInterface
     */
    private function httpResponseFromResponse(Response $Response, ResponseInterface $TargetHttpResponse)
    {
        // Write json data to body
        $TargetHttpResponse->getBody()->write($Response->asString());

        return $TargetHttpResponse->withHeader('Content-Type', ['application/json']);
    }

    /**
     * @param Request $Request
     * @return ReflectionMethod
     * @throws MethodNotFound
     */
    private function getMethodForRequest($Request)
    {
        $methodName = $Request->getMethod();

        if ($this->VisibilityClass && !$this->VisibilityClass->hasMethod($methodName)) {
            throw new MethodNotFound();
        }

        if (!$this->SubjectClass->hasMethod($methodName)) {
            throw new MethodNotFound();
        }

        $Method = $this->SubjectClass->getMethod($methodName);
        if (!$Method->isPublic()) {
            throw new MethodNotFound();
        }

        if (!$Method->isStatic() && $this->isStatic) {
            throw new MethodNotFound();
        }

        return $Method;
    }

    /**
     * @param Request $Request
     * @return mixed
     * @throws MethodNotFound
     */
    private function invokeRequest(Request $Request)
    {
        $Method = $this->getMethodForRequest($Request);

        return $Method->invoke($this->subject, ...$this->getParametersForMethodAndRequest($Method, $Request));
    }

    /**
     * @param ReflectionMethod $Method
     * @param Request $Request
     * @return array
     * @throws InvalidParameter
     * @throws MissingParameter
     * @throws TooManyParameters
     */
    private function getParametersForMethodAndRequest(ReflectionMethod $Method, Request $Request)
    {
        $parameters = $Request->getParams();
        $methodParameters = $Method->getParameters();

        $isAssociative = $this->isAssociative($parameters);

        $n = 0;
        $parametersByPosition = [];
        foreach ($methodParameters as $ExpectedParameter) {
            $key = $isAssociative ? $ExpectedParameter->getName() : $n;
            if (!array_key_exists($key, $parameters)) {
                if (!$ExpectedParameter->isOptional()) {
                    throw new MissingParameter('Required parameter <' . $ExpectedParameter->getName() . '> was not passed');
                }

                if (count($parameters) == 0) {
                    continue;
                }

                $parametersByPosition[] = $ExpectedParameter->getDefaultValue() ?: null;
                continue;
            }

            $parameter = $parameters[$key];
            unset($parameters[$key]);

            $ExpectedClass = $ExpectedParameter->getClass();
            if (!is_null($ExpectedClass) && (!is_object($parameter) || !$ExpectedClass->isInstance($parameter))) {
                throw new InvalidParameter('Parameter <' . $ExpectedParameter->getName() . '> must be of type <' . $ExpectedClass->getName() . '>');
            }

            if ($ExpectedParameter->isArray() && !is_array($parameter)) {
                throw new InvalidParameter('Parameter <' . $ExpectedParameter->getName() . '> must be an array');
            }

            $parametersByPosition[] = $parameter;
            $n++;
        }

        if (count($parameters) == 0) {
            return $parametersByPosition;
        }

        if (isset($ExpectedParameter) && !$isAssociative) {
            // Check if the method has a variadic argument that will accept any number of arguments
            if (method_exists($ExpectedParameter, 'isVariadic') && $ExpectedParameter->isVariadic()) {
                return array_merge($parametersByPosition, $parameters);
            }
        }

        throw new TooManyParameters();
    }

    /**
     * @param array $array
     * @return bool
     */
    private function isAssociative(array $array)
    {
        if (key($array) !== 0) {
            return true;
        }

        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }
}
