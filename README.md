# JSON-RPC 2.0 Server supporting PSR-7
[![Build Status](https://app.travis-ci.com/procurios/JsonRpc.svg?branch=2.0)](https://app.travis-ci.com/github/procurios/JsonRpc)
[![Coverage Status](https://coveralls.io/repos/procurios/JsonRpc/badge.svg?branch=2.0&service=github)](https://coveralls.io/github/procurios/JsonRpc?branch=2.0)

## Server
The server is a *complete* implementation of the [JSON-RPC 2.0 specification](http://www.jsonrpc.org/specification).
The server will expose public methods of an object or a static class which can be optionally limited using an interface or specific parent class.

To encourage interface segregation there is no support for other methods like closures or global functions.

### Features

- Full specification including notifications, both parameters by name and by position and batch requests
- Default values are used for skipped arguments
- Variadic arguments are supported
- PSR-7 compatible: This server can directly handle implementations of ```Psr\Http\Message\ServerRequestInterface```, returning an implementation of ```Psr\Http\Message\ResponseInterface```, as defined in [PSR-7](http://www.php-fig.org/psr/psr-7/)

### Requirements
PHP >= 8.0

## Example

### Subject classes

#### MyInterface
```php
<?php
interface MyInterface
{
    public function foo();
}
```

#### MySubjectClass
```php
<?php
class MySubjectClass implements MyInterface
{
    public function foo()
    {
        return 'foo';
    }

    public function bar()
    {
        return 'bar';
    }
}
```

### Handle request directly

```php
<?php
use Procurios\Json\JsonRpc\Server;
use Procurios\Json\JsonRpc\Request\Request;

$requestData = json_decode(file_get_contents('php://input'), true);
$request = Request::fromArray($requestData);

$server = new Server(new MySubjectClass);
$response = $server->handleRequest($request);

header('Content-Type: application/json');
die($response->asString());
```

### Handle using PSR-15 RequestHandlerInterface

```php
<?php
use Procurios\Json\JsonRpc\RequestHandler;

$server = RequestHandler::create($myResponseFactory, new MySubjectClass);

// Use the current Psr\Http\Message\ServerRequestInterface implementation in your application
$request = MyRequestSource::getRequest();

$response = $server->handle($request);

MyResponseEmitter::emit($response);
```

### Limit subject to an interface

```php
<?php
use Procurios\Json\JsonRpc\Server;

$server = new Server(new MySubjectClass, MyInterface::class);

// Only the method foo will be available in this server, since bar is not part of the interface
```
