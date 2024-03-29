<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

use JsonException;

abstract class JsonResponse implements Response
{
    public function asString(): string
    {
        try {
            return json_encode($this->asArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $errorCode = ErrorResponse::INTERNAL_ERROR;
            return <<<JSON
                {
                    "jsonrpc": "2.0",
                    "error": {
                        "code": {$errorCode},
                        "message": "Unable to encode JSON"
                    },
                    "id": null
                }
                JSON;
        }
    }

    abstract protected function asArray(): array;
}
