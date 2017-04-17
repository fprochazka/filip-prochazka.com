<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

// enabled by Docker env variable
if (in_array(getenv('PRODUCTION'), ['true', 'false'], true)) {
//	$configurator->setDebugMode(getenv('PRODUCTION') === 'false');
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
