<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Request;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;

/**
 * @link http://www.jsonrpc.org/specification#batch
 */
class BatchRequestTest extends PHPUnit_Framework_TestCase
{
    public function testFromArray()
    {
        $batch = [];
        $num = 3;
        for ($i = 0; $i < $num; $i++) {
            $id = uniqid();
            $batch[$id] = [
                'jsonrpc' => '2.0',
                'method' => uniqid(),
                'id' => $id,
            ];
        }

        $BatchRequest = BatchRequest::fromArray($batch);
        $requests = $BatchRequest->getRequests();

        $this->assertCount($num, $requests);
        foreach ($requests as $Request) {
            $this->assertInstanceOf(Request::class, $Request);

            $id = $Request->getId();
            $this->assertArrayHasKey($id, $batch);
            $this->assertSame($batch[$id]['method'], $Request->getMethod());
        }
    }

    public function testFromArrayWithNonArrayValue()
    {
        $batch = [
            [
                'jsonrpc' => '2.0',
                'method' => 'foo',
                'id' => 123,
            ],
            'foo',
        ];

        $this->setExpectedException(InvalidArgumentException::class);
        BatchRequest::fromArray($batch);
    }

    public function testFromArrayWithInvalidArrayValue()
    {
        $batch = [
            [
                'method' => 'foo',
                'id' => 123,
            ],
        ];

        $this->setExpectedException(InvalidArgumentException::class);
        BatchRequest::fromArray($batch);
    }

    public function testThatConstructorAcceptsRequests()
    {
        $this->assertInstanceOf(BatchRequest::class, new BatchRequest([new Request('foo')]));
    }

    public function testThatConstructorDoesNotAcceptNonRequests()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new BatchRequest(['foo']);
    }
}
