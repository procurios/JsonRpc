<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Procurios\Json\JsonRpc\Response\BatchResponse;
use Procurios\Json\JsonRpc\Response\SuccessResponse;

/**
 *
 */
class BatchResponseTest extends PHPUnit_Framework_TestCase
{
    public function testThatConstructorAcceptsResponses()
    {
        $this->assertInstanceOf(BatchResponse::class, new BatchResponse([new SuccessResponse(null, 'foo')]));
    }

    public function testThatConstructorDoesNotAcceptNonResponses()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new BatchResponse(['foo']);
    }

    public function testThatConstructorDoesNotAcceptBatchResponses()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new BatchResponse([new BatchResponse([])]);
    }
}
