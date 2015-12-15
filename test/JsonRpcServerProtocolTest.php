<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test;

use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Response\BatchResponse;
use Procurios\Json\JsonRpc\Response\ErrorResponse;
use Procurios\Json\JsonRpc\Response\Response;
use Procurios\Json\JsonRpc\Server;
use Procurios\Json\JsonRpc\test\assets\MockSubjectClass;

/**
 * Test JSON-RPC protocol compatibility @link http://www.jsonrpc.org/specification
 */
class JsonRpcServerProtocolTest extends ServerTestBase
{
    const NON_EXISTING_METHOD = 'oof';

    public function testThatANotificationWillNotGetAReply()
    {
        $Request = $this->createRequest('bar');

        $Server = new Server(MockSubjectClass::class);
        $Response = $Server->handleRequest($Request);

        $this->assertInstanceOf(Response::class, $Response);
        $this->assertSame('', $Response->asString());
    }

    public function testThatNotificationCausingAnErrorWillNotGetAReply()
    {
        $Request = $this->createRequest(self::NON_EXISTING_METHOD);

        $Server = new Server(MockSubjectClass::class);
        $Response = $Server->handleRequest($Request);

        $this->assertInstanceOf(Response::class, $Response);
        $this->assertSame('', $Response->asString());
    }

    public function testThatABatchRequestWithOnlyNotificationsWillNotGetAReply()
    {
        $Request = new BatchRequest([
            $this->createRequest('foo'),
            $this->createRequest('bar'),
        ]);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleBatchRequest($Request);

        $this->assertInstanceOf(BatchResponse::class, $Response);
        $this->assertSame('', $Response->asString());
    }

    public function testThatPositiveResponseContainsRightValues()
    {
        $uniqueString = uniqid();
        $id = uniqid();
        $Request = $this->createRequest('foo', ['does ', ' equal ' . $uniqueString . '?'], $id);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertInstanceOf(Response::class, $Response);

        $expected = [
            'jsonrpc' => '2.0',
            'result' => 'does foo equal ' . $uniqueString . '?',
            'id' => $id,
        ];

        $json = $Response->asString();
        $this->assertInternalType('string', $json);

        $data = json_decode($json, true);
        $this->assertInternalType('array', $data);

        ksort($expected);
        ksort($data);

        $this->assertSame($expected, $data);
    }

    public function testThatByNameParametersArePassedCorrectly()
    {
        $Request = $this->createRequest('foo', ['suffix' => ' suffix', 'prefix' => 'prefix '], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertInstanceOf(Response::class, $Response);

        $json = $Response->asString();
        $data = json_decode($json, true);

        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('result', $data);
        $this->assertSame('prefix foo suffix', $data['result']);
    }

    public function testMethodNotFoundResponse()
    {
        $Request = $this->createRequest(self::NON_EXISTING_METHOD, [], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertValidErrorResponse($Response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testTooManyParameters()
    {
        $Request = $this->createRequest('foo', ['a', 'b', 'c'], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertValidErrorResponse($Response, ErrorResponse::INVALID_PARAMS);
    }

    public function testUnknownParameter()
    {
        $Request = $this->createRequest('foo', ['prefix' => 'a', 'infix' => 'b'], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertValidErrorResponse($Response, ErrorResponse::INVALID_PARAMS);
    }

    public function testParameterNotRequestedClass()
    {
        $Request = $this->createRequest('bar', ['object' => 'foo'], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertValidErrorResponse($Response, ErrorResponse::INVALID_PARAMS);
    }

    public function testParameterWrongClass()
    {
        $Request = $this->createRequest('bar', ['object' => $this], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertValidErrorResponse($Response, ErrorResponse::INVALID_PARAMS);
    }

    public function testParameterNotAnArray()
    {
        $Request = $this->createRequest('bar', ['array' => 'string'], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);

        $this->assertValidErrorResponse($Response, ErrorResponse::INVALID_PARAMS);
    }

    public function testMissingNamedArgument()
    {
        $Request = $this->createRequest('baz', ['c' => 3], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);
        $this->assertValidErrorResponse($Response, ErrorResponse::INVALID_PARAMS);
    }

    public function testMissingPositionalArgument()
    {
        $Request = $this->createRequest('baz', [], 123);

        $Server = new Server(new MockSubjectClass);
        $Response = $Server->handleRequest($Request);
        $this->assertValidErrorResponse($Response, ErrorResponse::INVALID_PARAMS);
    }
}
