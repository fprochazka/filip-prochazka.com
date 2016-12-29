<?php

declare(strict_types = 1);

namespace Fp\Blog;

class EntriesByTagNotFoundException extends \RuntimeException
{

	/** @var string */
	private $tag;

	public function __construct(string $tag, \Exception $previous = null)
	{
		parent::__construct(sprintf('No entries by tag %s were found', $tag), 0, $previous);
		$this->tag = $tag;
	}

	public function getTag(): string
	{
		return $this->tag;
	}

}
