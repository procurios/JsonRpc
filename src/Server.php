<?php
declare(strict_types=1);
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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use TypeError;

/**
 * JSON-RPC 2.0 server
 */
class Server implements RequestHandlerInterface
{
    /** @var null|object The subject object or null if the subject is a class */
    private ?object $subject = null;
    private ReflectionClass $subjectClass;
    private ?ReflectionClass $visibilityClass = null;
    private bool $isStatic;

    /**
     * @param string|object $subject Either an object or a class name to expose
     * @param string|null $visibilityClass A parent class or interface to which the exposed methods will be limited
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        string|object $subject,
        ?string $visibilityClass = null
    ) {
        if (is_object($subject)) {
            $this->isStatic = false;
            $this->subject = $subject;
        } else {
            $this->isStatic = true;
        }

        try {
            $this->subjectClass = new ReflectionClass($subject);
        } catch (ReflectionException) {
            throw new InvalidArgumentException($subject . ' is not a valid class name');
        }

        if (!is_null($visibilityClass)) {
            try {
                $this->visibilityClass = new ReflectionClass($visibilityClass);
            } catch (ReflectionException) {
                throw new InvalidArgumentException($visibilityClass . ' is not a valid class name');
            }

            if (!is_subclass_of($subject, $visibilityClass)) {
                throw new InvalidArgumentException('Visibility class must be a parent class or interface of the given subject');
            }
        }
    }

    public function handleRequest(Request $request): Response
    {
        if (is_null($request->getId())) {
            return $this->handleNotification($request);
        }

        try {
            $result = $this->invokeRequest($request);

            return new SuccessResponse($request->getId(), $result);
        } catch (MethodNotFound) {
            return ErrorResponse::methodNotFound($request->getId());
        } catch (TooManyParameters) {
            return ErrorResponse::invalidParameters($request->getId(), 'Too many parameters');
        } catch (InvalidParameter | MissingParameter $e) {
            return ErrorResponse::invalidParameters($request->getId(), $e->getMessage() ?: null);
        }
    }

    private function handleNotification(Request $request): EmptyResponse
    {
        try {
            $this->invokeRequest($request);
        } catch (JsonRpcError) {
            // Do not report errors on notifications
        }

        return new EmptyResponse();
    }

    public function handleBatchRequest(BatchRequest $request): BatchResponse
    {
        return new BatchResponse(
            ...array_map(
                fn(Request $request) => $this->handleRequest($request),
                $request->getRequests()
            )
        );
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $jsonRpcRequest = Request::fromHttpRequest($request);
        } catch (CouldNotParse) {
            return $this->httpResponseFromResponse(ErrorResponse::parseError());
        } catch (InvalidArgumentException | TypeError $e) {
            return $this->httpResponseFromResponse(ErrorResponse::invalidRequest($e->getMessage()));
        }

        return $this->httpResponseFromResponse(
            $jsonRpcRequest instanceof BatchRequest
                ? $this->handleBatchRequest($jsonRpcRequest)
                : $this->handleRequest($jsonRpcRequest)
        );
    }

    private function httpResponseFromResponse(Response $response): ResponseInterface
    {
        $httpResponse = $this->responseFactory->createResponse();

        // Write json data to body
        $httpResponse->getBody()->write($response->asString());

        return $httpResponse->withHeader('Content-Type', ['application/json']);
    }

    /**
     * @throws MethodNotFound
     */
    private function getMethodForRequest(Request $request): ReflectionMethod
    {
        $methodName = $request->getMethod();

        if ($this->visibilityClass && !$this->visibilityClass->hasMethod($methodName)) {
            throw new MethodNotFound();
        }

        try {
            $method = $this->subjectClass->getMethod($methodName);
        } catch (ReflectionException) {
            throw new MethodNotFound();
        }

        if (!$method->isPublic()) {
            throw new MethodNotFound();
        }

        if ($this->isStatic && !$method->isStatic()) {
            throw new MethodNotFound();
        }

        return $method;
    }

    /**
     * @throws MethodNotFound|InvalidParameter|MissingParameter|TooManyParameters
     */
    private function invokeRequest(Request $request): mixed
    {
        $method = $this->getMethodForRequest($request);

        return $method->invoke(
            $this->subject,
            ...$this->getParametersForMethodAndRequest($method, $request)
        );
    }

    /**
     * @throws InvalidParameter|MissingParameter|TooManyParameters
     */
    private function getParametersForMethodAndRequest(ReflectionMethod $method, Request $request): array
    {
        $parameters = $request->getParams();
        $methodParameters = $method->getParameters();

        $isAssociative = $this->isAssociative($parameters);

        $n = 0;
        $parametersByPosition = [];
        foreach ($methodParameters as $expectedParameter) {
            $key = $isAssociative ? $expectedParameter->getName() : $n;
            if (!array_key_exists($key, $parameters)) {
                if (!$expectedParameter->isOptional()) {
                    throw new MissingParameter('Required parameter <' . $expectedParameter->getName() . '> was not passed');
                }

                if ($parameters === []) {
                    continue;
                }

                $parametersByPosition[] = $expectedParameter->getDefaultValue() ?: null;
                continue;
            }

            $parameter = $parameters[$key];
            unset($parameters[$key]);

            $parameterType = $expectedParameter->getType();
            if ($parameterType instanceof ReflectionNamedType) {
                $expectedTypeName = $parameterType->getName();
                if (!$parameterType->isBuiltin()) {
                    if ((!is_object($parameter) || !($parameter instanceof $expectedTypeName))) {
                        throw new InvalidParameter('Parameter <' . $expectedParameter->getName() . '> must be of type <' . $expectedTypeName . '>');
                    }
                } elseif ($expectedTypeName === 'array' && !is_array($parameter)) {
                    throw new InvalidParameter('Parameter <' . $expectedParameter->getName() . '> must be an array');
                }
            }

            $parametersByPosition[] = $parameter;
            $n++;
        }

        if ($parameters === []) {
            return $parametersByPosition;
        }

        // Check if the method has a variadic argument that will accept any number of arguments
        if (isset($expectedParameter) && !$isAssociative && $expectedParameter->isVariadic()) {
            return array_merge($parametersByPosition, $parameters);
        }

        throw new TooManyParameters();
    }

    private function isAssociative(array $array): bool
    {
        if (key($array) !== 0) {
            return true;
        }

        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }
}
