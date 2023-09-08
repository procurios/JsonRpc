<?php
declare(strict_types=1);
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc;

use InvalidArgumentException;
use Procurios\Json\JsonRpc\exception\CouldNotParse;
use Procurios\Json\JsonRpc\Request\BatchRequest;
use Procurios\Json\JsonRpc\Request\Request;
use Procurios\Json\JsonRpc\Response\ErrorResponse;
use Procurios\Json\JsonRpc\Response\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TypeError;

/**
 * JSON-RPC 2.0 server as request handler
 */
class RequestHandler implements RequestHandlerInterface
{
    public static function create(
        ResponseFactoryInterface $responseFactory,
        string|object $subject,
        ?string $visibilityClass = null
    ): RequestHandler {
        return new self(
            $responseFactory,
            new Server($subject, $visibilityClass)
        );
    }

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Server $server,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $jsonRpcRequest = Request::fromHttpRequest($request);
        } catch (CouldNotParse) {
            return $this->httpResponseFromResponse(ErrorResponse::parseError());
        } catch (InvalidArgumentException | TypeError $e) {
            return $this->httpResponseFromResponse(ErrorResponse::invalidRequest($e->getMessage()));
        }

        return $this->httpResponseFromResponse(
            $jsonRpcRequest instanceof BatchRequest
                ? $this->server->handleBatchRequest($jsonRpcRequest)
                : $this->server->handleRequest($jsonRpcRequest)
        );
    }

    private function httpResponseFromResponse(Response $response): ResponseInterface
    {
        $httpResponse = $this->responseFactory->createResponse();

        // Write json data to body
        $httpResponse->getBody()->write($response->asString());

        return $httpResponse->withHeader('Content-Type', ['application/json']);
    }
}
