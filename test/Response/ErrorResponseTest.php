<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use InvalidArgumentException;
use Procurios\Json\JsonRpc\Response\ErrorResponse;

/**
 *
 */
class ErrorResponseTest extends ResponseTestBase
{
	/**
	 * @dataProvider getValidIdValues
	 * @param mixed $id
	 */
	public function testValidIdValues($id)
	{
		$this->assertInstanceOf(ErrorResponse::class, new ErrorResponse($id, ErrorResponse::INTERNAL_ERROR, ''));
	}
	
	/**
	 * @dataProvider getInvalidIdValues
	 * @param mixed $id
	 */
	public function testInvalidIdValues($id)
	{
		$this->setExpectedException(InvalidArgumentException::class);
		new ErrorResponse($id, ErrorResponse::INTERNAL_ERROR, '');
	}
	
	/**
	 * @dataProvider getValidCodeValues
	 * @param mixed $code
	 */
	public function testValidCodeValues($code)
	{
		$this->assertInstanceOf(ErrorResponse::class, new ErrorResponse(123, $code, ''));
	}

	/**
	 * @return array
	 */
	public function getValidCodeValues()
	{
		return [
			'pre-defined' => [-32700],
			'reserved' => [-32005],
		];
	}
	
	/**
	 * @dataProvider getInvalidCodeValues
	 * @param mixed $code
	 */
	public function testInvalidCodeValues($code)
	{
		$this->setExpectedException(InvalidArgumentException::class);
		new ErrorResponse(123, $code, '');
	}

	/**
	 * @return array
	 */
	public function getInvalidCodeValues()
	{
		return [
			'string' => ['foo'],
			'null' => [null],
			'array' => [[]],
			'object' => [new \stdClass()],
			'out-of-range' => [100],
		];
	}
	
	/**
	 * @dataProvider getValidMessageValues
	 * @param mixed $message
	 */
	public function testValidMessageValues($message)
	{
		$this->assertInstanceOf(ErrorResponse::class, new ErrorResponse(123, ErrorResponse::INTERNAL_ERROR, $message));
	}

	/**
	 * @return array
	 */
	public function getValidMessageValues()
	{
		return [
			'empty string' => [''],
			'string' => ['foo bar'],
		];
	}
	
	/**
	 * @dataProvider getInvalidMessageValues
	 * @param mixed $message
	 */
	public function testInvalidMessageValues($message)
	{
		$this->setExpectedException(InvalidArgumentException::class);
		new ErrorResponse(123, ErrorResponse::INTERNAL_ERROR, $message);
	}

	/**
	 * @return array
	 */
	public function getInvalidMessageValues()
	{
		return [
			'null' => [null],
			'array' => [[]],
			'object' => [new \stdClass()],
			'integer' => [100],
		];
	}
}
