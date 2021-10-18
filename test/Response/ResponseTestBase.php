<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\Response;

use PHPUnit\Framework\TestCase;
use stdClass;

abstract class ResponseTestBase extends TestCase
{
    public function getValidIdValues(): iterable
    {
        return [
            'string' => ['foo'],
            'null' => [null],
            'number' => [123],
        ];
    }

    public function getInvalidIdValues(): iterable
    {
        return [
            'array' => [[]],
            'object' => [new stdClass()],
            'fraction' => [4.5],
        ];
    }
}
