<?php
/**
 * © 2015 Procurios - License MIT
 */
namespace Procurios\Json\JsonRpc\Request;

use InvalidArgumentException;

/**
 */
class BatchRequest
{
	/** @var Request[] */
	private $requests = [];

	/**
	 * @param Request[] $requests
	 */
	public function __construct(array $requests = [])
	{
		foreach ($requests as $Request) {
			if (!$Request instanceof Request) {
				throw new InvalidArgumentException;
			}

			$this->requests[] = $Request;
		}
	}

	/**
	 * @param array $batch
	 * @return BatchRequest
	 */
	public static function fromArray(array $batch)
	{
		$requests = [];
		foreach ($batch as $requestArray) {
			if (!is_array($requestArray)) {
				throw new InvalidArgumentException;
			}

			$requests[] = Request::fromArray($requestArray);
		}

		return new self($requests);
	}

	/**
	 * @return Request[]
	 */
	public function getRequests()
	{
		return $this->requests;
	}
}
