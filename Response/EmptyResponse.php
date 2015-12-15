<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

/**
 *
 */
class EmptyResponse implements Response
{
    /**
     * @return string
     */
    public function asString()
    {
        return '';
    }
}
