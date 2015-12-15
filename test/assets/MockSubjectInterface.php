<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\test\assets;

/**
 */
interface MockSubjectInterface
{
    /**
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    public function foo($prefix = null, $suffix = null);
}
