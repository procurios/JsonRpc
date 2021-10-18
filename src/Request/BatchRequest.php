<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Request;

use InvalidArgumentException;

class BatchRequest
{
    /** @var Request[] */
    private array $requests;

    public function __construct(Request ...$requests)
    {
        $this->requests = $requests;
    }

    public static function fromArray(array $batch): self
    {
        $requests = [];
        foreach ($batch as $requestArray) {
            if (!is_array($requestArray)) {
                throw new InvalidArgumentException();
            }

            $requests[] = Request::fromArray($requestArray);
        }

        return new self(...$requests);
    }

    /**
     * @return Request[]
     */
    public function getRequests(): array
    {
        return $this->requests;
    }
}
