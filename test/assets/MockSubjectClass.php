<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\assets;

use stdClass;

/**
 */
class MockSubjectClass extends MockSubjectParent implements MockSubjectInterface
{
	/**
	 * @param string $prefix
	 * @param string $suffix
	 * @return string
	 */
	public function foo($prefix = null, $suffix = null)
	{
		return $prefix . 'foo' . $suffix;
	}

	/**
	 * @param array $array
	 * @param stdClass $object
	 * @return string
	 */
	public static function bar(array $array = [], stdClass $object = null)
	{
		return 'tender';
	}

	/**
	 * @param string $a
	 * @param string $b
	 * @param string $c
	 * @return array
	 */
	public static function baz($a, $b = 'b', $c = null)
	{
		return [
			'a' => $a,
			'b' => $b,
			'c' => $c,
		];
	}

	/**
	 * @param mixed ...$v
	 * @return int
	 */
	public static function qux(...$v)
	{
		return implode(',', $v);
	}

	/**
	 * @return string
	 */
	protected static function quux()
	{
		return self::quuux();
	}

	/**
	 * @return string
	 */
	private static function quuux()
	{
		return 'quuux';
	}
}
