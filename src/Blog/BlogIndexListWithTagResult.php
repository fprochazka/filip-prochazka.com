<?php

declare(strict_types = 1);

namespace Fp\Blog;

use Kdyby\StrictObjects\Scream;

class BlogIndexListWithTagResult
{
	use Scream;

	/** @var BlogIndexEntry[] */
	public $results;

	/** @var string */
	public $tagTitle;

	/**
	 * @param BlogIndexEntry[] $results
	 * @param string $tagTitle
	 */
	public function __construct(array $results, string $tagTitle)
	{
		$this->results = $results;
		$this->tagTitle = $tagTitle;
	}

}
