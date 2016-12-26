<?php

declare(strict_types = 1);

namespace Fp;

use Kdyby\StrictObjects\Scream;
use Nette\Utils\Html;
use Nette\Utils\Json;

class FaviconsLoader
{
	use Scream;

	/** @var string */
	private $wwwDir;

	public function __construct(string $wwwDir)
	{
		$this->wwwDir = $wwwDir;
	}

	/** @return Html[] */
	public function getMetadata(): array
	{
		$path = $this->wwwDir . '/dist/icons-stats.json';
		if (!file_exists($path)) {
			throw new \Exception('Run webpack to generate icons-stats.json file');
		}

		$meta = Json::decode(file_get_contents($path), Json::FORCE_ARRAY);
		if (!array_key_exists('html', $meta)) {
			throw new \Exception('icons-stats.json is broken, missing html section');
		}

		$tags = [];
		foreach ($meta['html'] as $htmlElement) {
			$tags[] = Html::el()->setHtml($htmlElement);
		}

		return $tags;
	}

}
