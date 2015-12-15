<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Response;

use InvalidArgumentException;

/**
 *
 */
class BatchResponse implements Response
{
    /** @var Response[] */
    private $responses;

    /**
     * @param Response[] $responses
     */
    public function __construct(array $responses)
    {
        foreach ($responses as $Response) {
            if (!$Response instanceof Response) {
                throw new InvalidArgumentException;
            }

            if ($Response instanceof BatchResponse) {
                throw new InvalidArgumentException;
            }

            if ($Response instanceof EmptyResponse) {
                continue;
            }

            $this->responses[] = $Response;
        }
    }

    /**
     * @return string
     */
    public function asString()
    {
        if (count($this->responses) == 0) {
            return '';
        }

        return '[' .
            implode(',',
                array_map(function (Response $Response)
                    {
                        return $Response->asString();
                    },
                    $this->responses
                )
            ) .
        ']';
    }
}
