<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Request;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;
use TypeError;

/**
 * @link http://www.jsonrpc.org/specification#batch
 */
class BatchRequestTest extends TestCase
{
    public function testFromArray(): void
    {
        $batch = [];
        $num = 3;
        for ($i = 0; $i < $num; $i++) {
            $id = uniqid(more_entropy: false);
            $batch[$id] = [
                'jsonrpc' => '2.0',
                'method' => uniqid(more_entropy: false),
                'id' => $id,
            ];
        }

        $batchRequest = BatchRequest::fromArray($batch);
        $requests = $batchRequest->getRequests();

        self::assertCount($num, $requests);
        foreach ($requests as $request) {
            self::assertInstanceOf(Request::class, $request);

            $id = $request->getId();
            self::assertArrayHasKey($id, $batch);
            self::assertSame($batch[$id]['method'], $request->getMethod());
        }
    }

    public function testFromArrayWithNonArrayValue(): void
    {
        $batch = [
            [
                'jsonrpc' => '2.0',
                'method' => 'foo',
                'id' => 123,
            ],
            'foo',
        ];

        $this->expectException(InvalidArgumentException::class);
        BatchRequest::fromArray($batch);
    }

    public function testFromArrayWithInvalidArrayValue(): void
    {
        $batch = [
            [
                'method' => 'foo',
                'id' => 123,
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        BatchRequest::fromArray($batch);
    }

    public function testThatConstructorAcceptsRequests(): void
    {
        self::assertInstanceOf(BatchRequest::class, new BatchRequest(new Request('foo')));
    }

    public function testThatConstructorDoesNotAcceptNonRequests(): void
    {
        $this->expectException(TypeError::class);
        new BatchRequest('foo');
    }
}
