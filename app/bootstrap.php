<?php

require __DIR__ . '/../vendor/autoload.php';

// hack for https proxies
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
	if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' && isset($_SERVER['SERVER_PORT']) && substr($_SERVER['SERVER_PORT'], 0, 2) === "80") { // https over proxy
		$_SERVER['HTTPS'] = 'On';
		$_SERVER['SERVER_PORT'] = 443;

	} elseif ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'http' && isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 80) { // http over proxy
		$_SERVER['HTTPS'] = 'Off';
		$_SERVER['SERVER_PORT'] = 80;
	}
}

$configurator = new Nette\Configurator;

// enabled by Docker env variable
if (in_array(getenv('PRODUCTION'), ['true', 'false'], true)) {
	$configurator->setDebugMode(getenv('PRODUCTION') === 'false');
}
//$configurator->setDebugMode('23.75.345.200'); // enable for your remote IP
$configurator->enableTracy(__DIR__ . '/../var/log');

$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(__DIR__ . '/../var/temp');

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addParameters(['environment' => $configurator->isDebugMode() ? 'dev' : 'prod']);

$container = $configurator->createContainer();

return $container;
