<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

class SuccessResponse extends JsonResponse
{
    public function __construct(private string|int|null $id, private mixed $result)
    {
    }

    protected function asArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $this->result,
            'id' => $this->id,
        ];
    }
}
