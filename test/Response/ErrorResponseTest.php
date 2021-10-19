<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use InvalidArgumentException;
use Procurios\Json\JsonRpc\Response\ErrorResponse;
use Procurios\Json\JsonRpc\Response\JsonResponse;
use stdClass;
use TypeError;

class ErrorResponseTest extends ResponseTestBase
{
    /**
     * @dataProvider getValidIdValues
     */
    public function testValidIdValues(mixed $id): void
    {
        $this->assertInstanceOf(ErrorResponse::class, new ErrorResponse($id, ErrorResponse::INTERNAL_ERROR, ''));
    }

    /**
     * @dataProvider getInvalidIdValues
     */
    public function testInvalidIdValues(mixed $id): void
    {
        $this->expectException(TypeError::class);
        new ErrorResponse($id, ErrorResponse::INTERNAL_ERROR, '');
    }

    /**
     * @dataProvider getValidCodeValues
     */
    public function testValidCodeValues(int $code): void
    {
        $this->assertInstanceOf(ErrorResponse::class, new ErrorResponse(123, $code, ''));
    }

    public function getValidCodeValues(): iterable
    {
        return [
            'pre-defined' => [-32700],
            'reserved' => [-32005],
        ];
    }

    /**
     * @dataProvider getInvalidCodeValues
     */
    public function testInvalidCodeValues(mixed $code): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            new ErrorResponse(123, $code, '');
        } catch (TypeError $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    public function getInvalidCodeValues(): iterable
    {
        return [
            'string' => ['foo'],
            'null' => [null],
            'array' => [[]],
            'object' => [new stdClass()],
            'out-of-range' => [100],
        ];
    }

    /**
     * @dataProvider getValidMessageValues
     */
    public function testValidMessageValues(string $message): void
    {
        $this->assertInstanceOf(ErrorResponse::class, new ErrorResponse(123, ErrorResponse::INTERNAL_ERROR, $message));
    }

    public function getValidMessageValues(): iterable
    {
        return [
            'empty string' => [''],
            'string' => ['foo bar'],
        ];
    }

    /**
     * @dataProvider getInvalidMessageValues
     */
    public function testInvalidMessageValues(mixed $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            new ErrorResponse(123, ErrorResponse::INTERNAL_ERROR, $message);
        } catch (TypeError $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    public function getInvalidMessageValues(): iterable
    {
        return [
            'null' => [null],
            'array' => [[]],
            'object' => [new stdClass()],
            'integer' => [100],
        ];
    }

    public function testDefaultErrorResponseForInvalidJson(): void
    {
        $response = new class extends JsonResponse {
            protected function asArray(): array
            {
                return [
                    fopen(__FILE__, 'rb'),
                ];
            }
        };
        $json = $response->asString();
        $data = json_decode($json, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('jsonrpc', $data);
        self::assertSame('2.0', $data['jsonrpc']);

        self::assertArrayHasKey('error', $data);
        self::assertIsArray($data['error']);
        self::assertArrayHasKey('code', $data['error']);
        self::assertSame(ErrorResponse::INTERNAL_ERROR, $data['error']['code']);
        self::assertArrayHasKey('message', $data['error']);

        self::assertArrayHasKey('id', $data);
    }
}
