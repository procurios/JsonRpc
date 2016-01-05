<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

/**
 *
 */
abstract class JsonResponse implements Response
{
    /**
     * @return string
     */
    public function asString()
    {
        return json_encode($this->asArray());
    }

    /**
     * @return array
     */
    abstract protected function asArray();
}
