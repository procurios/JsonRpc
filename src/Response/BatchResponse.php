<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

use InvalidArgumentException;

class BatchResponse implements Response
{
    /** @var Response[] */
    private array $responses;

    public function __construct(Response ...$responses)
    {
        $this->responses = [];
        foreach ($responses as $response) {
            if ($response instanceof EmptyResponse) {
                continue;
            }

            if ($response instanceof self) {
                throw new InvalidArgumentException();
            }

            $this->responses[] = $response;
        }
    }

    public function asString(): string
    {
        if ($this->responses === []) {
            return '';
        }

        return '[' .
            implode(',',
                array_map(
                    static fn (Response $response) => $response->asString(),
                    $this->responses
                )
            ) .
        ']';
    }
}
