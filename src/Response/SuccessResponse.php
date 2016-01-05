<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

use InvalidArgumentException;

/**
 *
 */
class SuccessResponse extends JsonResponse
{
    /** @var string|int|null */
    private $id;
    /** @var mixed */
    private $result;

    /**
     * @param string|int|null $id
     * @param mixed $result
     */
    public function __construct($id, $result)
    {
        if (!is_scalar($id) && !is_null($id)) {
            throw new InvalidArgumentException();
        }

        $this->id = $id;
        $this->result = $result;
    }

    /**
     * @return array
     */
    protected function asArray()
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $this->result,
            'id' => $this->id,
        ];
    }
}
