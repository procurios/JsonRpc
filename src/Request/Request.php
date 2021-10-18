<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Request;

use InvalidArgumentException;
use JsonException;
use Procurios\Json\JsonRpc\exception\CouldNotParse;
use Psr\Http\Message\ServerRequestInterface;

class Request
{
    private array $params = [];
    private string|int|null $id = null;

    public function __construct(private string $method)
    {
    }

    /**
     * @throws CouldNotParse
     */
    public static function fromHttpRequest(ServerRequestInterface $serverRequest): self|BatchRequest
    {
        $body = $serverRequest->getBody();
        $jsonString = $body->__toString();
        try {
            $data = json_decode($jsonString, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CouldNotParse(previous: $e);
        }

        if (is_null($data) && strtolower($jsonString) !== 'null') {
            throw new CouldNotParse();
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Request should be an object or an array of objects');
        }

        // If the first element is an array, treat the data as a batch request
        if (count($data) > 0 && is_array(reset($data))) {
            return BatchRequest::fromArray($data);
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        self::validateData($data);

        $request = new self($data['method']);
        if (array_key_exists('params', $data)) {
            $request->setParams($data['params']);
        }
        if (array_key_exists('id', $data)) {
            $request->setId($data['id']);
        }

        return $request;
    }

    private static function validateData(array $data): void
    {
        if (!array_key_exists('jsonrpc', $data)) {
            throw new InvalidArgumentException('Missing jsonrpc member');
        }
        if ($data['jsonrpc'] !== '2.0') {
            throw new InvalidArgumentException('Member jsonrpc must be exactly "2.0"');
        }
        if (!array_key_exists('method', $data)) {
            throw new InvalidArgumentException('Missing method member');
        }
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->setParams($params);

        return $clone;
    }

    private function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function withId(string|int|null $id): self
    {
        $clone = clone $this;
        $clone->setId($id);

        return $clone;
    }

    /**
     * @param string|int|null $id
     */
    private function setId(string|int|null $id): void
    {
        $this->id = $id;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }
}
