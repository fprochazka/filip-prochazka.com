<?php

declare(strict_types = 1);

namespace Fp\Blog\Rss;

use DateTimeImmutable;
use Kdyby\StrictObjects\Scream;
use Nette\Utils\Html;

class RssItem
{
	use Scream;

	/** @var string */
	public $title;

	/** @var string */
	public $link;

	/** @var \DateTimeImmutable */
	public $pubDate;

	/** @var Html */
	public $description;

	/** @var string */
	public $guid;

	public function __construct(
		string $title,
		string $link,
		DateTimeImmutable $pubDate,
		Html $description,
		string $guid
	)
	{
		$this->title = $title;
		$this->link = $link;
		$this->pubDate = $pubDate;
		$this->description = $description;
		$this->guid = $guid;
	}

}
