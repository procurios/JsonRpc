<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

class EmptyResponse implements Response
{
    public function asString(): string
    {
        return '';
    }
}
