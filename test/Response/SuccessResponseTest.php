<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use InvalidArgumentException;
use Procurios\Json\JsonRpc\Response\SuccessResponse;
use TypeError;

class SuccessResponseTest extends ResponseTestBase
{
    /**
     * @dataProvider getValidIdValues
     */
    public function testValidIdValues(mixed $id): void
    {
        self::assertInstanceOf(SuccessResponse::class, new SuccessResponse($id, ''));
    }

    /**
     * @dataProvider getInvalidIdValues
     */
    public function testInvalidIdValues(mixed $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            new SuccessResponse($id, '');
        } catch (TypeError $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }
}
