<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

interface Response
{
    public function asString(): string;
}
