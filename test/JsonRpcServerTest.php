<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test;

use InvalidArgumentException;
use PHPUnit_Framework_MockObject_MockObject;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;
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

/**
 * Tests behavior of the Server class that is specific to this implementation
 */
class JsonRpcServerTest extends ServerTestBase
{
    /**
     * Test that the constants used in these tests have the values as defined in the protocol
     * @dataProvider getConstantNames
     * @param string $constantName
     * @param int $expectedValue
     */
    public function testConstants($constantName, $expectedValue)
    {
        $this->assertSame($expectedValue, constant(ErrorResponse::class . '::' . $constantName), $constantName);
    }

    /**
     * @return array
     */
    public function getConstantNames()
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
     * @param mixed $subject
     */
    public function testValidSubjectArguments($subject)
    {
        $this->assertInstanceof(Server::class, new Server($subject));
    }

    /**
     * @return array
     */
    public function getValidSubjectArguments()
    {
        return [
            'object' => [new MockSubjectClass()],
            'class' => [MockSubjectClass::class],
        ];
    }

    /**
     * @dataProvider getInValidSubjectArguments
     * @param mixed $subject
     */
    public function testInValidSubjectArguments($subject)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new Server($subject);
    }

    /**
     * @return array
     */
    public function getInValidSubjectArguments()
    {
        return [
            'null' => [null],
            'array' => [[]],
            'string' => [MockSubjectClass::class . 'not'],
        ];
    }

    /**
     * @dataProvider getValidVisibilityClasses
     * @param mixed $visibilityClass
     */
    public function testValidVisibilityClasses($visibilityClass)
    {
        $this->assertInstanceOf(Server::class, new Server(MockSubjectClass::class, $visibilityClass));
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
        new Server(MockSubjectClass::class, $visibilityClass);
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

    public function testThatHandleServerRequestCallsHandleRequestWithRightParameter()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => uniqid(),
            'id' => uniqid(),
            'params' => [uniqid() => uniqid()],
        ];

        $ServerRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($data)));
        $ServerResponse = $this->createServerResponseWithBody($this->createBodyWithContents(''));

        /** @var PHPUnit_Framework_MockObject_MockObject|Server $Server */
        $Server = $this->getMockBuilder(Server::class)
            ->disableOriginalConstructor()
            ->setMethods(['handleRequest'])
            ->getMock()
        ;

        $Response = $this->getMockBuilder(Response::class)->getMock();
        $RequestParameter = null;
        $Server->expects($this->once())
            ->method('handleRequest')
            ->willReturnCallback(function (Request $Request) use (&$RequestParameter, $Response)
            {
                $RequestParameter = $Request;
                return $Response;
            })
        ;

        $Server->handleServerRequest($ServerRequest, $ServerResponse);

        /** @var Request $RequestParameter */
        $this->assertInstanceOf(Request::class, $RequestParameter);
        $this->assertSame($data['method'], $RequestParameter->getMethod());
        $this->assertSame($data['id'], $RequestParameter->getId());
        $this->assertSame($data['params'], $RequestParameter->getParams());
    }

    public function testThatHandleServerRequestCallsHandleBatchRequestWithRightParameter()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => uniqid(),
            'id' => uniqid(),
            'params' => [uniqid() => uniqid()],
        ];

        $ServerRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode([$data])));
        $ServerResponse = $this->createServerResponseWithBody($this->createBodyWithContents(''));

        /** @var PHPUnit_Framework_MockObject_MockObject|Server $Server */
        $Server = $this->getMockBuilder(Server::class)
            ->disableOriginalConstructor()
            ->setMethods(['handleBatchRequest'])
            ->getMock()
        ;

        $Response = $this->getMockBuilder(Response::class)->getMock();
        $RequestParameter = null;
        $Server->expects($this->once())
            ->method('handleBatchRequest')
            ->willReturnCallback(function (BatchRequest $Request) use (&$RequestParameter, $Response)
            {
                $RequestParameter = $Request;
                return $Response;
            })
        ;

        $Server->handleServerRequest($ServerRequest, $ServerResponse);

        /** @var BatchRequest $RequestParameter */
        $this->assertInstanceOf(BatchRequest::class, $RequestParameter);

        $requests = $RequestParameter->getRequests();
        $FirstRequest = reset($requests);
        $this->assertSame($data['method'], $FirstRequest->getMethod());
        $this->assertSame($data['id'], $FirstRequest->getId());
        $this->assertSame($data['params'], $FirstRequest->getParams());
    }

    public function testParseError()
    {
        $ServerRequest = $this->createServerRequestWithBody($this->createBodyWithContents('{'));
        $ServerResponse = $this->createServerResponseWithBody($this->createBodyWithContents(''));

        $Server = new Server(new MockSubjectClass());
        $Response = $Server->handleServerRequest($ServerRequest, $ServerResponse);
        $json = $Response->getBody()->__toString();
        $data = json_decode($json, true);

        $this->assertValidErrorResponseData($data, ErrorResponse::PARSE_ERROR);
    }

    public function testInvalidRequestError()
    {
        $invalidRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
            'params' => 'bar',
        ];

        $ServerRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($invalidRequestData)));
        $ServerResponse = $this->createServerResponseWithBody($this->createBodyWithContents(''));

        $Server = new Server(new MockSubjectClass());
        $Response = $Server->handleServerRequest($ServerRequest, $ServerResponse);
        $json = $Response->getBody()->__toString();
        $data = json_decode($json, true);

        $this->assertValidErrorResponseData($data, ErrorResponse::INVALID_REQUEST);
    }

    public function testValidNotification()
    {
        $validRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
        ];

        $headers = [];

        $ServerRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($validRequestData)));
        $ServerResponse = $this->createServerResponseWithBody($this->createBodyWithContents(''), $headers);

        $Server = new Server(new MockSubjectClass());
        $Response = $Server->handleServerRequest($ServerRequest, $ServerResponse);
        $json = $Response->getBody()->__toString();

        $this->assertSame('', $json);

        $headers = array_change_key_case($headers, CASE_LOWER);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertSame(['application/json'], $headers['content-type']);
    }

    public function testValidRequest()
    {
        $id = uniqid();
        $validRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
            'id' => $id,
            'params' => [$id],
        ];

        $headers = [];

        $ServerRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode($validRequestData)));
        $ServerResponse = $this->createServerResponseWithBody($this->createBodyWithContents(''), $headers);

        $Server = new Server(new MockSubjectClass());
        $Response = $Server->handleServerRequest($ServerRequest, $ServerResponse);
        $json = $Response->getBody()->__toString();
        $data = json_decode($json, true);

        $this->assertInternalType('array', $data);

        ksort($data);
        $this->assertSame(['id', 'jsonrpc', 'result'], array_keys($data));
        $this->assertSame($id, $data['id']);
        $this->assertSame('2.0', $data['jsonrpc']);
        $this->assertSame($id . 'foo', $data['result']);

        $headers = array_change_key_case($headers, CASE_LOWER);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertSame(['application/json'], $headers['content-type']);
    }

    public function testValidBatchRequest()
    {
        $id = uniqid();
        $validRequestData = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
            'id' => $id,
            'params' => [$id],
        ];

        $headers = [];

        $ServerRequest = $this->createServerRequestWithBody($this->createBodyWithContents(json_encode([$validRequestData])));
        $ServerResponse = $this->createServerResponseWithBody($this->createBodyWithContents(''), $headers);

        $Server = new Server(new MockSubjectClass());
        $Response = $Server->handleServerRequest($ServerRequest, $ServerResponse);
        $json = $Response->getBody()->__toString();
        $batchData = json_decode($json, true);

        $this->assertInternalType('array', $batchData);
        $this->assertCount(1, $batchData);
        $this->assertArrayHasKey(0, $batchData);

        $data = $batchData[0];
        $this->assertInternalType('array', $data);

        ksort($data);
        $this->assertSame(['id', 'jsonrpc', 'result'], array_keys($data));
        $this->assertSame($id, $data['id']);
        $this->assertSame('2.0', $data['jsonrpc']);
        $this->assertSame($id . 'foo', $data['result']);

        $headers = array_change_key_case($headers, CASE_LOWER);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertSame(['application/json'], $headers['content-type']);
    }

    public function testDefaultValueForSkippedArguments()
    {
        $Request = $this->createRequest('baz', ['a' => 1, 'c' => 3], 123);
        $expected = [
            'a' => 1,
            'b' => 'b',
            'c' => 3,
        ];

        $Server = new Server(new MockSubjectClass());
        $Response = $Server->handleRequest($Request);
        $json = $Response->asString();

        $data = json_decode($json, true);
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('result', $data);
        $this->assertInternalType('array', $data['result']);
        ksort($data['result']);
        $this->assertSame($expected, $data['result']);
    }

    public function testThatVariadicArgumentAcceptsManyValues()
    {
        $values = range(1, mt_rand(10, 20));
        $expectedResult = implode(',', $values);
        $Request = $this->createRequest('qux', $values, 123);

        $Server = new Server(new MockSubjectClass());
        $Response = $Server->handleRequest($Request);
        $json = $Response->asString();

        $data = json_decode($json, true);
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('result', $data);
        $this->assertSame($expectedResult, $data['result']);
    }

    public function testThatAnInterfaceWillLimitAvailableMethods()
    {
        $ValidRequest = $this->createRequest('foo', [], 123);
        $InvalidRequest = $this->createRequest('bar', [], 123);

        $Server = new Server(new MockSubjectClass(), MockSubjectInterface::class);
        $Response = $Server->handleRequest($ValidRequest);
        $json = $Response->asString();

        $data = json_decode($json, true);
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('result', $data);
        $this->assertSame('foo', $data['result']);

        $Response = $Server->handleRequest($InvalidRequest);

        $this->assertValidErrorResponse($Response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatObjectMethodsAreNotAvailableOnStaticClass()
    {
        $Request = $this->createRequest('foo', [], 123);

        $Server = new Server(MockSubjectClass::class);
        $Response = $Server->handleRequest($Request);
        $this->assertValidErrorResponse($Response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatProtectedMethodsAreNotAvailable()
    {
        $Request = $this->createRequest('quux', [], 123);

        $Server = new Server(MockSubjectClass::class);
        $Response = $Server->handleRequest($Request);
        $this->assertValidErrorResponse($Response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatPrivateMethodsAreNotAvailable()
    {
        $Request = $this->createRequest('quuux', [], 123);

        $Server = new Server(MockSubjectClass::class);
        $Response = $Server->handleRequest($Request);
        $this->assertValidErrorResponse($Response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testThatHandleRequestIsUsedForBatchRequests()
    {
        $requests = [
            'foo' => $this->createRequest('foo'),
            'bar' => $this->createRequest('bar'),
            'baz' => $this->createRequest('baz'),
        ];
        $BatchRequest = new BatchRequest($requests);

        /** @var PHPUnit_Framework_MockObject_MockObject|Server $Server */
        $Server = $this->getMockBuilder(Server::class)
            ->disableOriginalConstructor()
            ->setMethods(['handleRequest'])
            ->getMock()
        ;

        $Server->expects($this->any())
            ->method('handleRequest')
            ->willReturnCallback(function (Request $Request) use (&$requests)
            {
                $method = $Request->getMethod();
                $this->assertArrayHasKey($method, $requests);
                unset($requests[$method]);
                return new SuccessResponse(null, $method);
            });

        $BatchResponse = $Server->handleBatchRequest($BatchRequest);

        // Every request should be passed to handleRequest
        $this->assertCount(0, $requests);

        $json = $BatchResponse->asString();
        $data = json_decode($json, true);

        $this->assertInternalType('array', $data);
        $results = [];
        foreach ($data as $responseData) {
            $this->assertArrayHasKey('result', $responseData);
            $results[] = $responseData['result'];
        }
        sort($results);

        $this->assertSame(['bar', 'baz', 'foo'], $results);
    }

    /**
     * @param string $contents
     * @return PHPUnit_Framework_MockObject_MockObject|StreamInterface
     */
    private function createBodyWithContents($contents)
    {
        $Body = $this->getMockBuilder(StreamInterface::class)
            ->getMock()
        ;

        $Body->expects($this->any())
            ->method('write')
            ->willReturnCallback(function ($data) use (&$contents)
            {
                $contents .= $data;
            })
        ;

        $Body->expects($this->any())
            ->method('__toString')
            ->willReturnCallback(function () use (&$contents)
            {
                return $contents;
            })
        ;

        return $Body;
    }

    /**
     * @param StreamInterface $Body
     * @return PHPUnit_Framework_MockObject_MockObject|ServerRequestInterface
     */
    private function createServerRequestWithBody($Body)
    {
        $ServerRequest = $this->getMockBuilder(ServerRequestInterface::class)
            ->getMock()
        ;

        $ServerRequest->expects($this->any())
            ->method('getBody')
            ->willReturn($Body)
        ;

        return $ServerRequest;
    }

    /**
     * @param StreamInterface $Body
     * @param array $headers
     * @return PHPUnit_Framework_MockObject_MockObject|ResponseInterface
     */
    private function createServerResponseWithBody($Body, array &$headers = [])
    {
        $ServerRequest = $this->getMockBuilder(ResponseInterface::class)
            ->getMock()
        ;

        $ServerRequest->expects($this->any())
            ->method('getBody')
            ->willReturn($Body)
        ;

        $ServerRequest->expects($this->any())
            ->method('withHeader')
            ->willReturnCallback(function ($headerName, $headerValue) use (&$headers, $ServerRequest)
            {
                $headers[$headerName] = (array)$headerValue;
                return $ServerRequest;
            })
        ;

        return $ServerRequest;
    }
}
