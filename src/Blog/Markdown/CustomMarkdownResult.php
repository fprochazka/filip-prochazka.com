<?php

declare(strict_types = 1);

namespace Fp\Blog\Markdown;

use Kdyby\StrictObjects\Scream;

class CustomMarkdownResult
{
	use Scream;

	/** @var string */
	public $htmlContent;

	/** @var string */
	public $htmlIntro;

	/** @var string */
	public $description;

	/** @var string */
	public $title;

	/** @var mixed[] list of [string src, string alt] */
	public $images = [];

	/** @var mixed[] map of [Key => Value] */
	public $metadata;

	public function __construct(
		string $htmlContent,
		string $htmlIntro,
		string $description,
		string $title,
		array $images,
		array $metadata
	)
	{
		$this->htmlContent = $htmlContent;
		$this->htmlIntro = $htmlIntro;
		$this->description = $description;
		$this->title = $title;
		$this->images = $images;
		$this->metadata = $metadata;
	}

}
