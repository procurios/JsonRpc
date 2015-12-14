<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use PHPUnit_Framework_TestCase;

/**
 */
abstract class ResponseTestBase extends PHPUnit_Framework_TestCase
{
	/**
	 * @return array
	 */
	public function getValidIdValues()
	{
		return [
				'string' => ['foo'],
				'null' => [null],
				'number' => [123],
				'fraction' => [4.5],
		];
	}

	/**
	 * @return array
	 */
	public function getInvalidIdValues()
	{
		return [
				'array' => [[]],
				'object' => [new \stdClass()],
		];
	}
}
