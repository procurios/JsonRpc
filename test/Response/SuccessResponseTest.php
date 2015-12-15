<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use InvalidArgumentException;
use Procurios\Json\JsonRpc\Response\SuccessResponse;

/**
 *
 */
class SuccessResponseTest extends ResponseTestBase
{
    /**
     * @dataProvider getValidIdValues
     * @param mixed $id
     */
    public function testValidIdValues($id)
    {
        $this->assertInstanceOf(SuccessResponse::class, new SuccessResponse($id, ''));
    }

    /**
     * @dataProvider getInvalidIdValues
     * @param mixed $id
     */
    public function testInvalidIdValues($id)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new SuccessResponse($id, '');
    }


}
