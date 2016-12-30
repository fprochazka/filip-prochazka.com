<?php

declare(strict_types = 1);

namespace Fp\Sitemap;

use DateTimeImmutable;
use Kdyby\StrictObjects\Scream;

class SitemapItem
{
	use Scream;

	const CHANGES_MONTHLY = 'monthly';
	const CHANGES_DAILY = 'daily';
	const CHANGES_ALWAYS = 'always';

	/** @var string */
	public $loc;

	/** @var \DateTimeImmutable */
	public $lastmod;

	/** @var string */
	public $changefreq;

	/** @var string */
	public $priority;

	public function __construct(
		string $loc,
		DateTimeImmutable $lastmod,
		string $changefreq,
		string $priority
	)
	{
		$this->loc = $loc;
		$this->lastmod = $lastmod;
		$this->changefreq = $changefreq;
		$this->priority = $priority;
	}

}
