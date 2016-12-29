<?php

declare(strict_types = 1);

namespace Fp\Blog;

class EntryBySlugNotFoundException extends \RuntimeException
{

	/** @var string */
	private $slug;

	public function __construct(string $slug, \Exception $previous = null)
	{
		parent::__construct(sprintf('Entry by slug %s not found', $slug), 0, $previous);
		$this->slug = $slug;
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

}
