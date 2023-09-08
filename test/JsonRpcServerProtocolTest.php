<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test;

use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Response\ErrorResponse;
use Procurios\Json\JsonRpc\Server;
use Procurios\Json\JsonRpc\test\assets\MockSubjectClass;

/**
 * Test JSON-RPC protocol compatibility @link http://www.jsonrpc.org/specification
 */
class JsonRpcServerProtocolTest extends ServerTestBase
{
    private const NON_EXISTING_METHOD = 'oof';

    public function testThatANotificationWillNotGetAReply(): void
    {
        $request = $this->createRequest('bar');

        $server = new Server($this->getResponseFactory(), MockSubjectClass::class);
        $response = $server->handleRequest($request);

        self::assertSame('', $response->asString());
    }

    public function testThatNotificationCausingAnErrorWillNotGetAReply(): void
    {
        $request = $this->createRequest(self::NON_EXISTING_METHOD);

        $server = new Server($this->getResponseFactory(), MockSubjectClass::class);
        $response = $server->handleRequest($request);

        self::assertSame('', $response->asString());
    }

    public function testThatABatchRequestWithOnlyNotificationsWillNotGetAReply(): void
    {
        $request = new BatchRequest(
            $this->createRequest('foo'),
            $this->createRequest('bar'),
        );

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleBatchRequest($request);

        self::assertSame('', $response->asString());
    }

    public function testThatPositiveResponseContainsRightValues(): void
    {
        $uniqueString = uniqid(more_entropy: false);
        $id = uniqid(more_entropy: false);
        $request = $this->createRequest('foo', ['does ', ' equal ' . $uniqueString . '?'], $id);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        $expected = [
            'jsonrpc' => '2.0',
            'result' => 'does foo equal ' . $uniqueString . '?',
            'id' => $id,
        ];

        $json = $response->asString();
        self::assertIsString($json);

        $data = json_decode($json, true);
        self::assertIsArray($data);

        ksort($expected);
        ksort($data);

        self::assertSame($expected, $data);
    }

    public function testThatByNameParametersArePassedCorrectly(): void
    {
        $request = $this->createRequest('foo', ['suffix' => ' suffix', 'prefix' => 'prefix '], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        $json = $response->asString();
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertArrayHasKey('result', $data);
        self::assertSame('prefix foo suffix', $data['result']);
    }

    public function testMethodNotFoundResponse(): void
    {
        $request = $this->createRequest(self::NON_EXISTING_METHOD, [], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        self::assertValidErrorResponse($response, ErrorResponse::METHOD_NOT_FOUND);
    }

    public function testTooManyParameters(): void
    {
        $request = $this->createRequest('foo', ['a', 'b', 'c'], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        self::assertValidErrorResponse($response, ErrorResponse::INVALID_PARAMS);
    }

    public function testUnknownParameter(): void
    {
        $request = $this->createRequest('foo', ['prefix' => 'a', 'infix' => 'b'], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        self::assertValidErrorResponse($response, ErrorResponse::INVALID_PARAMS);
    }

    public function testParameterNotRequestedClass(): void
    {
        $request = $this->createRequest('bar', ['object' => 'foo'], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        self::assertValidErrorResponse($response, ErrorResponse::INVALID_PARAMS);
    }

    public function testParameterWrongClass(): void
    {
        $request = $this->createRequest('bar', ['object' => $this], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        self::assertValidErrorResponse($response, ErrorResponse::INVALID_PARAMS);
    }

    public function testParameterNotAnArray(): void
    {
        $request = $this->createRequest('bar', ['array' => 'string'], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);

        self::assertValidErrorResponse($response, ErrorResponse::INVALID_PARAMS);
    }

    public function testMissingNamedArgument(): void
    {
        $request = $this->createRequest('baz', ['c' => 3], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);
        self::assertValidErrorResponse($response, ErrorResponse::INVALID_PARAMS);
    }

    public function testMissingPositionalArgument(): void
    {
        $request = $this->createRequest('baz', [], 123);

        $server = new Server($this->getResponseFactory(), new MockSubjectClass());
        $response = $server->handleRequest($request);
        self::assertValidErrorResponse($response, ErrorResponse::INVALID_PARAMS);
    }
}
