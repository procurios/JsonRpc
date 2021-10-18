<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Procurios\Json\JsonRpc\Response\BatchResponse;
use Procurios\Json\JsonRpc\Response\SuccessResponse;
use TypeError;

class BatchResponseTest extends TestCase
{
    public function testThatConstructorAcceptsResponses(): void
    {
        self::assertInstanceOf(BatchResponse::class, new BatchResponse(new SuccessResponse(null, 'foo')));
    }

    public function testThatConstructorDoesNotAcceptNonResponses(): void
    {
        $this->expectException(TypeError::class);
        new BatchResponse('foo');
    }

    public function testThatConstructorDoesNotAcceptBatchResponses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BatchResponse(new BatchResponse());
    }
}
