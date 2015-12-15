<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

use InvalidArgumentException;

/**
 *
 */
class ErrorResponse extends JsonResponse
{
    // Invalid JSON was received by the server. An error occurred on the server while parsing the JSON text.
    const PARSE_ERROR = -32700;
    // The JSON sent is not a valid Request object.
    const INVALID_REQUEST = -32600;
    // The method does not exist / is not available.
    const METHOD_NOT_FOUND = -32601;
    // Invalid method parameter(s).
    const INVALID_PARAMS = -32602;
    // Internal JSON-RPC error.
    const INTERNAL_ERROR = -32603;

    /** @var string|int|null */
    private $id;
    /** @var int */
    private $code;
    /** @var string */
    private $message;

    /**
     * @param string|int|null $id
     * @param int $code
     * @param string $message
     */
    public function __construct($id, $code, $message)
    {
        if (!is_scalar($id) && !is_null($id)) {
            throw new InvalidArgumentException;
        }

        if (!is_int($code)) {
            throw new InvalidArgumentException;
        }

        if (!in_array($code, self::getReservedErrorCodes()) && ($code < -32099 || $code > -32000)) {
            throw new InvalidArgumentException;
        }

        if (!is_string($message)) {
            throw new InvalidArgumentException;
        }

        $this->id = $id;
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * @param string|int|null $id
     * @param string $message
     * @return ErrorResponse
     */
    public static function methodNotFound($id, $message = null)
    {
        return new self($id, self::METHOD_NOT_FOUND, $message ?: 'Method not found');
    }

    /**
     * @param string $message
     * @return ErrorResponse
     */
    public static function parseError($message = null)
    {
        return new self(null, self::PARSE_ERROR, $message ?: 'Parse error');
    }

    /**
     * @param string $message
     * @return ErrorResponse
     */
    public static function invalidRequest($message = null)
    {
        return new self(null, self::INVALID_REQUEST, $message ?: 'Invalid Request');
    }

    /**
     * @param string|int|null $id
     * @param string $message
     * @return ErrorResponse
     */
    public static function invalidParameters($id, $message = null)
    {
        return new self($id, self::INVALID_PARAMS, $message ?: 'Invalid method parameter(s)');
    }

    /**
     * @return array
     */
    protected function asArray()
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

    /**
     * @return array
     */
    private static function getReservedErrorCodes()
    {
        return [
            self::PARSE_ERROR,
            self::INVALID_REQUEST,
            self::METHOD_NOT_FOUND,
            self::INVALID_PARAMS,
            self::INTERNAL_ERROR,
        ];
    }
}
