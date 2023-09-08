<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;
use Procurios\Json\JsonRpc\Response\BatchResponse;
use Procurios\Json\JsonRpc\Response\ErrorResponse;
use Procurios\Json\JsonRpc\Response\Response;
use Procurios\Json\JsonRpc\Response\SuccessResponse;
use Procurios\Json\JsonRpc\Server;
use Procurios\Json\JsonRpc\test\assets\MockSubjectClass;
use Procurios\Json\JsonRpc\test\assets\MockSubjectInterface;
use Procurios\Json\JsonRpc\test\assets\MockSubjectParent;
use Procurios\Json\JsonRpc\test\assets\OtherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use TypeError;

/**
 * Tests behavior of the Server class that is specific to this implementation
 */
class JsonRpcServerTest extends ServerTestBase
{
    /**
     * Test that the constants used in these tests have the values as defined in the protocol
     * @dataProvider getConstantNames
     */
    public function testConstants(string $constantName, int $expectedValue): void
    {
        self::assertSame($expectedValue, constant(ErrorResponse::class . '::' . $constantName), $constantName);
    }

    public function getConstantNames(): iterable
    {
        return [
            ['PARSE_ERROR', -32700],
            ['INVALID_REQUEST', -32600],
            ['METHOD_NOT_FOUND', -32601],
            ['INVALID_PARAMS', -32602],
            ['INTERNAL_ERROR', -32603],
        ];
    }

    /**
     * @dataProvider getValidSubjectArguments
     */
    public function testValidSubjectArguments(mixed $subject): void
    {
        self::assertInstanceof(Server::class, new Server($this->getResponseFactory(), $subject));
    }

    public function getValidSubjectArguments(): iterable
    {
        return [
            'object' => [new MockSubjectClass()],
            'class' => [MockSubjectClass::class],
        ];
    }

    /**
     * @dataProvider getInValidSubjectArguments
     */
    public function testInValidSubjectArguments(mixed $subject): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            new Server($this->getResponseFactory(), $subject);
        } catch (TypeError $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    public function getInValidSubjectArguments(): iterable
    {
        return [
            'null' => [null],
            'array' => [[]],
            'string' => [MockSubjectClass::class . 'not'],
        ];
    }

    /**
     * @dataProvider getValidVisibilityClasses
     */
    public function testValidVisibilityClasses(string $visibilityClass): void
    {
        self::assertInstanceOf(Server::class, new Server($this->getResponseFactory(), MockSubjectClass::class, $visibilityClass));
    }

    public function getValidVisibilityClasses(): iterable
    {
        return [
            'interface' => [MockSubjectInterface::class],
            'parent class' => [MockSubjectParent::class],
        ];
    }

    /**
     * @dataProvider getInvalidVisibilityClasses
     */
    public function testInvalidVisibilityClasses(mixed $visibilityClass): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            new Server($this->getResponseFactory(), MockSubjectClass::class, $visibilityClass);
        } catch (TypeError $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    public function getInvalidVisibilityClasses(): iterable
    {
        return [
            'array' => [[]],
            'string that is not a class' => ['foo bar'],
            'other interface' => [OtherInterface::class],
            'not parent class' => [self::class],
        ];
    }

    public function testThatHandleCallsHandleRequestWithRightParameter(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => uniqid(more_entropy: false),
            'id' => uniqid(more_entropy: false),
            'params' => [uniqid(more_entropy: false) => uniqid(more_entropy: false)],
        ];

        $serverRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($data)));

        /** @var MockObject|Server $server */
        $server = $this->getMockBuilder(Server::class)
            ->setConstructorArgs([$this->getResponseFactory(), new MockSubjectClass()])
            ->onlyMethods(['handleRequest'])
            ->getMock();

        $response = $this->getMockBuilder(Response::class)->getMock();
        $requestParameter = null;
        $server->expects($this->once())
            ->method('handleRequest')
            ->willReturnCallback(function (Request $request) use (&$requestParameter, $response) {
                $requestParameter = $request;
                return $response;
            });

        $server->handle($serverRequest);

        /** @var Request $requestParameter */
        self::assertInstanceOf(Request::class, $requestParameter);
        self::assertSame($data['method'], $requestParameter->getMethod());
        self::assertSame($data['id'], $requestParameter->getId());
        self::assertSame($data['params'], $requestParameter->getParams());
    }

    public function testThatHandleServerRequestCallsHandleBatchRequestWithRightParameter(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => uniqid(more_entropy: false),
            'id' => uniqid(more_entropy: false),
            'params' => [uniqid(more_entropy: false) => uniqid(more_entropy: false)],
        ];

        $serverRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode([$data])));

        /** @var MockObject|Server $server */
        $server = $this->getMockBuilder(Server::class)
            ->setConstructorArgs([$this->getResponseFactory(), new MockSubjectClass()])
            ->onlyMethods(['handleBatchRequest'])
            ->getMock();

        $response = $this->getMockBuilder(BatchResponse::class)
            ->disableOriginalConstructor()
            ->getMock();
        $requestParameter = null;
        $server->expects($this->once())
            ->method('handleBatchRequest')
            ->willReturnCallback(function (BatchRequest $request) use (&$requestParameter, $response) {
                $requestParameter = $request;
                return $response;
            });

        $server->handle($serverRequest);

        /** @var BatchRequest $requestParameter */
        self::assertInstanceOf(BatchRequest::class, $requestParameter);

        $requests = $requestParameter->getRequests();
        $firstRequest = reset($requests);
        self::assertSame($data['method'], $firstRequest->getMethod());
        self::assertSame($data['id'], $firstRequest->getId());
        self::assertSame($data['params'], $firstRequest->getParams());
    }

    public function testParseError(): void
    {
        $serverRequest = $this->createServerRequestWithBody($this->createBodyWithContents('{'));

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handle($serverRequest);
        $json = $response->getBody()->__toString();
        $data = json_decode($json, true);

        self::assertValidErrorResponseData($data, ErrorResponse::PARSE_ERROR);
    }

    public function testInvalidRequestError(): void
    {
        $invalidRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
            'params' => 'bar',
        ];

        $serverRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($invalidRequestData)));

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handle($serverRequest);
        $json = $response->getBody()->__toString();
        $data = json_decode($json, true);

        self::assertValidErrorResponseData($data, ErrorResponse::INVALID_REQUEST);
    }

    public function testValidNotification(): void
    {
        $validRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
        ];

        $serverRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($validRequestData)));

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handle($serverRequest);
        $json = $response->getBody()->__toString();

        self::assertSame('', $json);

        $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);
        self::assertArrayHasKey('content-type', $headers);
        self::assertSame(['application/json'], $headers['content-type']);
    }

    public function testValidRequest(): void
    {
        $id = uniqid(more_entropy: false);
        $validRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
            'id' => $id,
            'params' => [$id],
        ];

        $serverRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($validRequestData)));

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handle($serverRequest);
        $json = $response->getBody()->__toString();
        $data = json_decode($json, true);

        self::assertIsArray($data);

        ksort($data);
        self::assertSame(['id', 'jsonrpc', 'result'], array_keys($data));
        self::assertSame($id, $data['id']);
        self::assertSame('2.0', $data['jsonrpc']);
        self::assertSame($id . 'foo', $data['result']);

        $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);
        self::assertArrayHasKey('content-type', $headers);
        self::assertSame(['application/json'], $headers['content-type']);
    }

    public function testValidBatchRequest(): void
    {
        $id = uniqid(more_entropy: false);
        $validRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
            'id' => $id,
            'params' => [$id],
        ];

        $serverRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode([$validRequestData])));

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handle($serverRequest);
        $json = $response->getBody()->__toString();
        $batchData = json_decode($json, true);

        self::assertIsArray($batchData);
        self::assertCount(1, $batchData);
        self::assertArrayHasKey(0, $batchData);

        $data = $batchData[0];
        self::assertIsArray($data);

        ksort($data);
        self::assertSame(['id', 'jsonrpc', 'result'], array_keys($data));
        self::assertSame($id, $data['id']);
        self::assertSame('2.0', $data['jsonrpc']);
        self::assertSame($id . 'foo', $data['result']);

        $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);
        self::assertArrayHasKey('content-type', $headers);
        self::assertSame(['application/json'], $headers['content-type']);
    }

    public function testDefaultValueForSkippedArguments(): void
    {
        $request = $this->createRequest('baz', ['a' => 1, 'c' => 3], 123);
        $expected = [
            'a' => 1,
            'b' => 'b',
            'c' => 3,
        ];

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);
        $json = $response->asString();

        $data = json_decode($json, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('result', $data);
        self::assertIsArray($data['result']);
        ksort($data['result']);
        self::assertSame($expected, $data['result']);
    }

    public function testThatVariadicArgumentAcceptsManyValues(): void
    {
        $values = range(1, mt_rand(10, 20));
        $expectedResult = implode(',', $values);
        $request = $this->createRequest('qux', $values, 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);
        $json = $response->asString();

        $data = json_decode($json, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('result', $data);
        self::assertSame($expectedResult, $data['result']);
    }

    public function testThatAnInterfaceWillLimitAvailableMethods(): void
    {
        $validRequest = $this->createRequest('foo', [], 123);
        $invalidRequest = $this->createRequest('bar', [], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass(), MockSubjectInterface::class);
        $response = $server->handleRequest($validRequest);
        $json = $response->asString();

        $data = json_decode($json, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('result', $data);
        self::assertSame('foo', $data['result']);

        $response = $server->handleRequest($invalidRequest);

        self::assertValidErrorResponse($response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatObjectMethodsAreNotAvailableOnStaticClass(): void
    {
        $request = $this->createRequest('foo', [], 123);

        $server = new Server($this->getResponseFactory(), MockSubjectClass::class);
        $response = $server->handleRequest($request);
        self::assertValidErrorResponse($response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatProtectedMethodsAreNotAvailable(): void
    {
        $request = $this->createRequest('quux', [], 123);

        $server = new Server($this->getResponseFactory(), MockSubjectClass::class);
        $response = $server->handleRequest($request);
        self::assertValidErrorResponse($response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatPrivateMethodsAreNotAvailable(): void
    {
        $request = $this->createRequest('quuux', [], 123);

        $server = new Server($this->getResponseFactory(), MockSubjectClass::class);
        $response = $server->handleRequest($request);
        self::assertValidErrorResponse($response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatHandleRequestIsUsedForBatchRequests(): void
    {
        $requests = [
            'foo' => $this->createRequest('foo'),
            'bar' => $this->createRequest('bar'),
            'baz' => $this->createRequest('baz'),
        ];
        $batchRequest = new BatchRequest(...$requests);

        /** @var MockObject|Server $server */
        $server = $this->getMockBuilder(Server::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['handleRequest'])
            ->getMock();

        $server->method('handleRequest')
            ->willReturnCallback(function (Request $request) use (&$requests) {
                $method = $request->getMethod();
                self::assertArrayHasKey($method, $requests);
                unset($requests[$method]);
                return new SuccessResponse(null, $method);
            });

        $batchResponse = $server->handleBatchRequest($batchRequest);

        // Every request should be passed to handleRequest
        self::assertCount(0, $requests);

        $json = $batchResponse->asString();
        $data = json_decode($json, true);

        self::assertIsArray($data);
        $results = [];
        foreach ($data as $responseData) {
            self::assertArrayHasKey('result', $responseData);
            $results[] = $responseData['result'];
        }
        sort($results);

        self::assertSame(['bar', 'baz', 'foo'], $results);
    }

    private function createBodyWithContents(string $contents): MockObject|StreamInterface
    {
        $body = $this->getMockBuilder(StreamInterface::class)
            ->getMock();

        $body->method('write')
            ->willReturnCallback(function ($data) use (&$contents) {
                $contents .= $data;
                return strlen($data);
            });

        $body->method('__toString')
            ->willReturnCallback(function () use (&$contents) {
                return $contents;
            });

        return $body;
    }

    private function createServerRequestWithBody(StreamInterface $body): MockObject|ServerRequestInterface
    {
        $serverRequest = $this->getMockBuilder(ServerRequestInterface::class)
            ->getMock();

        $serverRequest->method('getBody')
            ->willReturn($body);

        return $serverRequest;
    }

    private function createServerResponseWithBody(
        StreamInterface $body,
        array &$headers = []
    ): MockObject|ResponseInterface {
        $serverRequest = $this->getMockBuilder(ResponseInterface::class)
            ->getMock();

        $serverRequest->method('getBody')
            ->willReturn($body);

        $serverRequest->method('withHeader')
            ->willReturnCallback(function ($headerName, $headerValue) use (&$headers, $serverRequest) {
                $headers[$headerName] = (array)$headerValue;
                return $serverRequest;
            });

        return $serverRequest;
    }
}
