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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

abstract class ServerTestBase extends TestCase
{
    private ?ResponseFactoryInterface $responseFactory = null;

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

    protected function getResponseFactory(): ResponseFactoryInterface
    {
        if ($this->responseFactory === null) {
            $factory = function (int $code, string $reasonPhrase): ResponseInterface {
                /** @var MockObject|ResponseInterface $response */
                $response = $this->createMock(ResponseInterface::class);
                $response->method('getStatusCode')->willReturn($code);
                $response->method('getReasonPhrase')->willReturn($reasonPhrase);

                $body = null;
                $response->method('getBody')->willReturnCallback(function () use (&$body) {
                    if ($body === null) {
                        /** @var MockObject|StreamInterface $body */
                        $body = $this->createMock(StreamInterface::class);

                        $contents = '';
                        $body->method('write')->willReturnCallback(function (string $newContent) use (&$contents) {
                            $contents .= $newContent;
                            return strlen($newContent);
                        });
                        $body->method('getContents')->willReturnCallback(function () use (&$contents) {
                            return $contents;
                        });
                        $body->method('__toString')->willReturnCallback(function () use (&$contents) {
                            return $contents;
                        });
                    }
                    return $body;
                });

                $headers = [];
                $response->method('withHeader')->willReturnCallback(function (string $name, $value) use (&$headers, &$response) {
                    $headers[$name] = (array)$value;
                    return $response;
                });
                $response->method('getHeaders')->willReturnCallback(function () use (&$headers) {
                    return $headers;
                });
                return $response;
            };

            $this->responseFactory = new class ($factory) implements ResponseFactoryInterface {
                /** @var callable */
                private $factory;

                public function __construct(callable $factory)
                {
                    $this->factory = $factory;
                }

                public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
                {
                    return ($this->factory)($code, $reasonPhrase);
                }
            };
        }
        return $this->responseFactory;
    }
}
