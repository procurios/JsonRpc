<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

use InvalidArgumentException;

class ErrorResponse extends JsonResponse
{
    // Invalid JSON was received by the server. An error occurred on the server while parsing the JSON text.
    public const PARSE_ERROR = -32700;
    // The JSON sent is not a valid Request object.
    public const INVALID_REQUEST = -32600;
    // The method does not exist / is not available.
    public const METHOD_NOT_FOUND = -32601;
    // Invalid method parameter(s).
    public const INVALID_PARAMS = -32602;
    // Internal JSON-RPC error.
    public const INTERNAL_ERROR = -32603;

    private const RESERVED_CODES = [
        self::PARSE_ERROR,
        self::INVALID_REQUEST,
        self::METHOD_NOT_FOUND,
        self::INVALID_PARAMS,
        self::INTERNAL_ERROR,
    ];

    public function __construct(private string|int|null $id, private int $code, private string $message)
    {
        if (($this->code < -32099 || $this->code > -32000) && !in_array($this->code, self::RESERVED_CODES, true)) {
            throw new InvalidArgumentException();
        }
    }

    public static function methodNotFound(string|int|null $id, string $message = null): self
    {
        return new self($id, self::METHOD_NOT_FOUND, $message ?: 'Method not found');
    }

    public static function parseError(string $message = null): self
    {
        return new self(null, self::PARSE_ERROR, $message ?: 'Parse error');
    }

    public static function invalidRequest(string $message = null): self
    {
        return new self(null, self::INVALID_REQUEST, $message ?: 'Invalid Request');
    }

    public static function invalidParameters(string|int|null $id, string $message = null): self
    {
        return new self($id, self::INVALID_PARAMS, $message ?: 'Invalid method parameter(s)');
    }

    protected function asArray(): array
    {
        return [
                'jsonrpc' => '2.0',
                'error' => [
                        'code' => $this->code,
                        'message' => $this->message,
                ],
                'id' => null,// $this->id,
        ];
    }
}
