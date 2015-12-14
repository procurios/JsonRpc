<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Request;

use InvalidArgumentException;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @link http://www.jsonrpc.org/specification#request_object
 */
class RequestTest extends PHPUnit_Framework_TestCase
{
	public function testFromServerRequest()
	{
		$jsonRequestObject = [
			'jsonrpc' => '2.0',
			'method' => uniqid(),
			'id' => uniqid(),
		];

		$ServerRequest = $this->createServerRequestForString(json_encode($jsonRequestObject));

		$Request = Request::fromHttpRequest($ServerRequest);

		$this->assertInstanceof(Request::class, $Request);
		$this->assertSame($jsonRequestObject['method'], $Request->getMethod());
		$this->assertSame($jsonRequestObject['id'], $Request->getId());
	}

	public function testBatchFromServerRequest()
	{
		$jsonRequestObject = [
			'jsonrpc' => '2.0',
			'method' => uniqid(),
			'id' => uniqid(),
		];

		$ServerRequest = $this->createServerRequestForString(json_encode([$jsonRequestObject]));

		$Request = Request::fromHttpRequest($ServerRequest);

		$this->assertInstanceof(BatchRequest::class, $Request);

		$requests = $Request->getRequests();
		$this->assertCount(1, $requests);

		$FirstRequest = reset($requests);
		$this->assertSame($jsonRequestObject['method'], $FirstRequest->getMethod());
		$this->assertSame($jsonRequestObject['id'], $FirstRequest->getId());
	}

	public function testThatParamsAndIdAreCaseSensitive()
	{
		$data = [
			'jsonrpc' => '2.0',
			'method' => 'foo',
			'ID' => 123,
			'PARAMS' => ['foo', 'bar'],
		];

		$ServerRequest = $this->createServerRequestForString(json_encode($data));

		$Request = Request::fromHttpRequest($ServerRequest);

		$this->assertNull($Request->getId());
		$this->assertSame([], $Request->getParams());
	}

	/**
	 * @dataProvider getInvalidDataSets
	 * @param mixed $data
	 */
	public function testInvalidDataSets($data)
	{
		$this->setExpectedException(InvalidArgumentException::class);

		$ServerRequest = $this->createServerRequestForString(json_encode($data));
		Request::fromHttpRequest($ServerRequest);
	}

	/**
	 * @return array
	 */
	public function getInvalidDataSets()
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
			'objectAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => new \stdClass()]],
		];
	}

	/**
	 * @dataProvider getValidDataSets
	 * @param mixed $data
	 */
	public function testValidDataSets($data)
	{
		$ServerRequest = $this->createServerRequestForString(json_encode($data));
		$this->assertInstanceOf(Request::class, Request::fromHttpRequest($ServerRequest));
	}

	/**
	 * @return array
	 */
	public function getValidDataSets()
	{
		return [
			'stringAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 'bar']],
			'nullAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => null]],
			'numberAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 123]],
			'fractionAsId' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 3.3]],
			'arrayAsParams' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 123, 'params' => ['foo', 'bar', 'baz']]],
			'associativeArrayAsParams' => [['jsonrpc' => '2.0', 'method' => 'foo', 'id' => 123, 'params' => ['foo' => 'bar', 'baz' => 'qux']]],
		];
	}

	public function testThatWithParamsDoesNotChangeRequest()
	{
		$Request = new Request('foo');
		$this->assertSame('foo', $Request->getMethod());
		$this->assertSame([], $Request->getParams());
		$this->assertNull($Request->getId());

		$OtherRequest = $Request->withParams(['foo' => 'bar']);
		$this->assertSame('foo', $Request->getMethod());
		$this->assertSame([], $Request->getParams());
		$this->assertNull($Request->getId());

		$this->assertSame('foo', $OtherRequest->getMethod());
		$this->assertSame(['foo' => 'bar'], $OtherRequest->getParams());
		$this->assertNull($OtherRequest->getId());
	}

	public function testThatWithIdDoesNotChangeRequest()
	{
		$Request = new Request('foo');
		$this->assertSame('foo', $Request->getMethod());
		$this->assertSame([], $Request->getParams());
		$this->assertNull($Request->getId());

		$OtherRequest = $Request->withId(123);
		$this->assertSame('foo', $Request->getMethod());
		$this->assertSame([], $Request->getParams());
		$this->assertNull($Request->getId());

		$this->assertSame('foo', $OtherRequest->getMethod());
		$this->assertSame([], $OtherRequest->getParams());
		$this->assertSame(123, $OtherRequest->getId());
	}

	/**
	 * @param string $string
	 * @return ServerRequestInterface
	 */
	private function createServerRequestForString($string)
	{
		/** @var PHPUnit_Framework_MockObject_MockObject|StreamInterface $Body */
		$Body = $this->getMockBuilder(StreamInterface::class)
			->getMock()
		;
		$Body->expects($this->any())
			->method('__toString')
			->willReturn($string)
		;

		/** @var PHPUnit_Framework_MockObject_MockObject|ServerRequestInterface $ServerRequest */
		$ServerRequest = $this->getMockBuilder(ServerRequestInterface::class)
			->getMock()
		;
		$ServerRequest->expects($this->any())
			->method('getBody')
			->willReturn($Body)
		;

		return $ServerRequest;
	}
}
