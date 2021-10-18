<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test;

use JsonException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Procurios\Json\JsonRpc\Request\Request;
use Procurios\Json\JsonRpc\Response\Response;

abstract class ServerTestBase extends TestCase
{
    protected static function assertValidErrorResponse(mixed $response, int $expectedCode): void
    {
        self::assertInstanceOf(Response::class, $response);

        $json = $response->asString();
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            self::assertTrue(false, $e->getMessage());
            return;
        }

        self::assertValidErrorResponseData($data, $expectedCode);
    }

    protected static function assertValidErrorResponseData(mixed $data, int $expectedCode): void
    {
        self::assertIsArray($data);

        ksort($data);
        self::assertSame(['error', 'id', 'jsonrpc'], array_keys($data));
        self::assertSame('2.0', $data['jsonrpc']);
        self::assertNull($data['id']);
        self::assertIsArray($data['error']);

        $error = $data['error'];
        ksort($error);
        unset($error['data']); // Ignore any data for this test
        self::assertSame(['code', 'message'], array_keys($error));
        self::assertSame($expectedCode, $error['code']);
        self::assertIsString($error['message']);
        self::assertNotEmpty($error['message']);
    }

    protected function createRequest(
        string $methodName,
        array $params = [],
        string|int|null $id = null
    ): MockObject|Request {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request->method('getMethod')
            ->willReturn($methodName);

        $request->method('getParams')
            ->willReturn($params);

        $request->method('getId')
            ->willReturn($id);

        return $request;
    }
}
