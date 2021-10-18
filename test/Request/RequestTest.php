<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Request;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use stdClass;
use TypeError;

/**
 * @link http://www.jsonrpc.org/specification#request_object
 */
class RequestTest extends TestCase
{
    public function testFromServerRequest(): void
    {
        $jsonRequestObject = [
            'jsonrpc' => '2.0',
            'method' => uniqid(more_entropy: false),
            'id' => uniqid(more_entropy: false),
        ];

        $serverRequest = $this->createServerRequestForString(json_encode($jsonRequestObject));

        $request = Request::fromHttpRequest($serverRequest);

        self::assertInstanceof(Request::class, $request);
        self::assertSame($jsonRequestObject['method'], $request->getMethod());
        self::assertSame($jsonRequestObject['id'], $request->getId());
    }

    public function testBatchFromServerRequest(): void
    {
        $jsonRequestObject = [
            'jsonrpc' => '2.0',
            'method' => uniqid(more_entropy: false),
            'id' => uniqid(more_entropy: false),
        ];

        $serverRequest = $this->createServerRequestForString(json_encode([$jsonRequestObject]));

        $request = Request::fromHttpRequest($serverRequest);

        self::assertInstanceof(BatchRequest::class, $request);

        $requests = $request->getRequests();
        self::assertCount(1, $requests);

        $firstRequest = reset($requests);
        self::assertSame($jsonRequestObject['method'], $firstRequest->getMethod());
        self::assertSame($jsonRequestObject['id'], $firstRequest->getId());
    }

    public function testThatParamsAndIdAreCaseSensitive(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'foo',
            'ID' => 123,
            'PARAMS' => ['foo', 'bar'],
        ];

        $serverRequest = $this->createServerRequestForString(json_encode($data));

        $request = Request::fromHttpRequest($serverRequest);

        self::assertNull($request->getId());
        self::assertSame([], $request->getParams());
    }

    /**
     * @dataProvider getInvalidDataSets
     */
    public function testInvalidDataSets(mixed $data): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $serverRequest = $this->createServerRequestForString(json_encode($data));
            Request::fromHttpRequest($serverRequest);
        } catch (TypeError $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    public function getInvalidDataSets(): iterable
    {
        return [
            'string' => ['foo'],
            'noJsonRpc' => [['method' => 'foo', 'id' => 123]],
            'wrongJsonRpc' => [['jsonrpc' => 2, 'method' => 'foo', 'id' => 123]],
            'wrongCaseJsonRpc' => [['jsonRPC' => '2.0', 'method' => 'foo', 'id' => 123]],
            'noMethod' => [['jsonrpc' => '2.0', 'id' => 123]],
            'methodNotAString' => [['jsonrpc' => '2.0', 'method' => [], 'id' => 123]],
            'wrongCaseMethod' => [['jsonrpc' => '2.0', 'METHOD' => 'foo', 'id' => 123]],
            'paramsNotAnArray' => [['jsonrpc' => '2.0', 'method' => 'foo', 'params' => 'bar', 'id' => 123]],
            'arrayAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => []]],
            'objectAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => new stdClass()]],
        ];
    }

    /**
     * @dataProvider getValidDataSets
     */
    public function testValidDataSets(array $data): void
    {
        $serverRequest = $this->createServerRequestForString(json_encode($data));
        self::assertInstanceOf(Request::class, Request::fromHttpRequest($serverRequest));
    }

    public function getValidDataSets(): iterable
    {
        return [
            'stringAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 'bar']],
            'nullAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => null]],
            'numberAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 123]],
            'fractionAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 3]],
            'arrayAsParams' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 123, 'params' => ['foo', 'bar', 'baz']]],
            'associativeArrayAsParams' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 123, 'params' => ['foo' => 'bar', 'baz' => 'qux']]],
        ];
    }

    public function testThatWithParamsDoesNotChangeRequest(): void
    {
        $request = new Request('foo');
        self::assertSame('foo', $request->getMethod());
        self::assertSame([], $request->getParams());
        self::assertNull($request->getId());

        $otherRequest = $request->withParams(['foo' => 'bar']);
        self::assertSame('foo', $request->getMethod());
        self::assertSame([], $request->getParams());
        self::assertNull($request->getId());

        self::assertSame('foo', $otherRequest->getMethod());
        self::assertSame(['foo' => 'bar'], $otherRequest->getParams());
        self::assertNull($otherRequest->getId());
    }

    public function testThatWithIdDoesNotChangeRequest(): void
    {
        $request = new Request('foo');
        self::assertSame('foo', $request->getMethod());
        self::assertSame([], $request->getParams());
        self::assertNull($request->getId());

        $otherRequest = $request->withId(123);
        self::assertSame('foo', $request->getMethod());
        self::assertSame([], $request->getParams());
        self::assertNull($request->getId());

        self::assertSame('foo', $otherRequest->getMethod());
        self::assertSame([], $otherRequest->getParams());
        self::assertSame(123, $otherRequest->getId());
    }

    private function createServerRequestForString(string $string): MockObject|ServerRequestInterface
    {
        /** @var MockObject|StreamInterface $body */
        $body = $this->getMockBuilder(StreamInterface::class)
            ->getMock();
        $body->method('__toString')
            ->willReturn($string);

        /** @var MockObject|ServerRequestInterface $serverRequest */
        $serverRequest = $this->getMockBuilder(ServerRequestInterface::class)
            ->getMock();
        $serverRequest->method('getBody')
            ->willReturn($body);

        return $serverRequest;
    }
}
