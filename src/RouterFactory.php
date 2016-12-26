<?php

namespace Fp;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use Nette\Http;

class RouterFactory
{

	/** @var bool */
	private $productionMode;

	public function __construct($productionMode, Http\IRequest $httpRequest)
	{
		$this->productionMode = (bool) $productionMode;
	}

	public function createRouter(): Nette\Application\IRouter
	{
		$router = new RouteList();

		$router[] = new Route('<presenter>[/<action>]', 'Homepage:default');

		return $router;
	}

}
