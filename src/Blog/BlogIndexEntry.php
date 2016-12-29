<?php

declare(strict_types = 1);

namespace Fp\Blog;

use DateTimeImmutable;
use Kdyby\StrictObjects\Scream;
use Nette\Utils\Html;

class BlogIndexEntry implements \JsonSerializable
{
	use Scream;

	/** @var string */
	public $file;

	/** @var string */
	public $title;

	/** @var string */
	public $slug;

	/** @var string|null */
	public $titleLink;

	/** @var string */
	public $description;

	/** @var string */
	public $contentSneakPeak;

	/** @var \DateTimeImmutable */
	public $publishedTime;

	/** @var string[] */
	public $tags;

	/** @var mixed[] list of [string src, string alt] */
	public $images;

	public function __construct(
		string $file,
		string $title,
		string $slug,
		string $titleLink = null,
		string $description,
		string $contentSneakPeak,
		DateTimeImmutable $publishedTime,
		array $tags,
		array $images
	)
	{
		$this->file = $file;
		$this->title = $title;
		$this->slug = $slug;
		$this->titleLink = $titleLink;
		$this->description = $description;
		$this->contentSneakPeak = $contentSneakPeak;
		$this->publishedTime = $publishedTime;
		$this->tags = $tags;
		$this->images = $images;
	}

	public function getContentTempFile(): string
	{
		return sprintf('%s-%s.html', dirname($this->file), $this->slug);
	}

	public function getContentSneakPeak(): Html
	{
		return Html::el()->setHtml($this->contentSneakPeak);
	}

	public function jsonSerialize()
	{
		$data = (array) $this;
		$data['publishedTime'] = $data['publishedTime']->format('Y-m-d H:i:s');
		return $data;
	}

	public static function createFromArray(array $data): self
	{
		return new static(
			$data['file'],
			$data['title'],
			$data['slug'],
			$data['titleLink'],
			$data['description'],
			$data['contentSneakPeak'],
			new DateTimeImmutable($data['publishedTime']),
			$data['tags'],
			$data['images']
		);
	}

}
