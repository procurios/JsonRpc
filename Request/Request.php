<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Request;

use InvalidArgumentException;
use Procurios\Json\JsonRpc\exception\CouldNotParse;
use Psr\Http\Message\ServerRequestInterface;

/**
 */
class Request
{
    /** @var string */
    private $method;
    /** @var array */
    private $params = [];
    /** @var string|int|null */
    private $id;

    /**
     * @param string $method
     */
    public function __construct($method)
    {
        if (!is_string($method)) {
            throw new InvalidArgumentException('Method member should be a string');
        }
        $this->method = $method;
    }

    /**
     * @param ServerRequestInterface $ServerRequest
     * @return BatchRequest|Request
     * @throws CouldNotParse
     */
    public static function fromHttpRequest(ServerRequestInterface $ServerRequest)
    {
        $Body = $ServerRequest->getBody();
        $jsonString = $Body->__toString();
        $data = json_decode($jsonString, true);

        if (is_null($data) && strtolower($jsonString) != 'null') {
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

    /**
     * @param array $data
     * @return Request
     */
    public static function fromArray(array $data)
    {
        self::validateData($data);

        $Request = new self($data['method']);
        if (array_key_exists('params', $data)) {
            $Request->setParams($data['params']);
        }
        if (array_key_exists('id', $data)) {
            $Request->setId($data['id']);
        }

        return $Request;
    }

    /**
     * @param array $data
     */
    private static function validateData(array $data)
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

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param array $params
     * @return Request
     */
    public function withParams($params)
    {
        $Clone = clone $this;
        $Clone->setParams($params);

        return $Clone;
    }

    /**
     * @param array $params
     */
    private function setParams($params)
    {
        if (!is_array($params)) {
            throw new InvalidArgumentException('Member params should be either an object or an array');
        }
        $this->params = $params;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param string|int|null $id
     * @return Request
     */
    public function withId($id)
    {
        $Clone = clone $this;
        $Clone->setId($id);

        return $Clone;
    }

    /**
     * @param string|int|null $id
     */
    private function setId($id)
    {
        if (!is_scalar($id) && !is_null($id)) {
            throw new InvalidArgumentException('Member id should be either a string, number or Null');
        }
        $this->id = $id;
    }

    /**
     * @return string|int|null
     */
    public function getId()
    {
        return $this->id;
    }
}
