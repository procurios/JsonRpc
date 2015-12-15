<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Procurios\Json\JsonRpc\Request\Request;
use Procurios\Json\JsonRpc\Response\Response;

/**
 */
abstract class ServerTestBase extends PHPUnit_Framework_TestCase
{
    /**
     * @param Response $Response
     * @param int $expectedCode
     */
    protected function assertValidErrorResponse($Response, $expectedCode)
    {
        $this->assertInstanceOf(Response::class, $Response);

        $json = $Response->asString();
        $data = json_decode($json, true);

        $this->assertValidErrorResponseData($data, $expectedCode);
    }
    /**
     * @param mixed $data
     * @param int $expectedCode
     */
    protected function assertValidErrorResponseData($data, $expectedCode)
    {
        $this->assertInternalType('array', $data);

        ksort($data);
        $this->assertSame(['error', 'id', 'jsonrpc'], array_keys($data));
        $this->assertSame('2.0', $data['jsonrpc']);
        $this->assertNull($data['id']);
        $this->assertInternalType('array', $data['error']);

        $error = $data['error'];
        ksort($error);
        unset($error['data']); // Ignore any data for this test
        $this->assertSame(['code', 'message'], array_keys($error));
        $this->assertSame($expectedCode, $error['code']);
        $this->assertInternalType('string', $error['message']);
        $this->assertNotEmpty($error['message']);
    }

    /**
     * @param string $methodName
     * @param array $params
     * @param string|int|null $id
     * @return PHPUnit_Framework_MockObject_MockObject|Request
     */
    protected function createRequest($methodName, array $params = [], $id = null)
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|Request $Request */
        $Request = $this->getMockBuilder(Request::class)
                ->disableOriginalConstructor()
                ->getMock()
        ;

        $Request->expects($this->any())
            ->method('getMethod')
            ->willReturn($methodName)
        ;

        $Request->expects($this->any())
            ->method('getParams')
            ->willReturn($params)
        ;

        $Request->expects($this->any())
            ->method('getId')
            ->willReturn($id)
        ;

        return $Request;
    }
}
