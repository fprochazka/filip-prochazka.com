<?php

declare(strict_types = 1);

namespace Fp\Blog\Markdown;

use Kdyby\StrictObjects\Scream;
use Nette\Utils\Strings;
use ParsedownExtraPlugin;

class CustomMarkdownProcessor extends ParsedownExtraPlugin
{

	use Scream;

	/** @var string[] */
	private $headingSlugs = [];

	/** @var mixed[] list of [int level, string title] */
	public $headings = [];

	/** @var mixed[] list of [string src, string alt] */
	public $images = [];

	/** @var mixed[] map of [Key => Value]*/
	public $metadata = [];

	/** @var string|null */
	public $titleLink;

	public function text($text)
	{
		list($metadata, $titleMarkup, $content) = preg_split('~^(\\#.+?)$~im', $text, 2, PREG_SPLIT_DELIM_CAPTURE);

		$this->metadata = self::parseMetadata($metadata);

		return parent::text($titleMarkup . $content);
	}

	protected function blockHeader($Line)
	{
		$block = parent::blockHeader($Line);
		if (is_array($block)) {
			$block['element']['attributes']['id'] = 'toc-' . $this->getHeadingId($block['element']['text']);

			$this->headings[] = [
				(int) substr($block['element']['name'], 1),
				$block['element']['text'],
			];
		}

		return $block;
	}

	protected function inlineImage($excerpt)
	{
		$image = parent::inlineImage($excerpt);
		if (is_array($image)) {
			$this->images[] = [
				$image['element']['attributes']['src'],
				$image['element']['attributes']['alt'],
			];
		}

		return $image;
	}

	private function getHeadingId($text)
	{
		$slug = Strings::webalize($text);
		$attributeId = $slug;
		if (!isset($this->headingSlugs[$slug])) {
			$this->headingSlugs[$slug] = 0;
		}
		if ($this->headingSlugs[$slug] > 0) {
			$attributeId .= '-' . $this->headingSlugs[$slug];
		}
		$this->headingSlugs[$slug]++;
		return $attributeId;
	}

	private static function parseMetadata(string $metadata): array
	{
		$meta = [];
		foreach (Strings::matchAll($metadata, '~^(?P<property>\S+)\:(?P<value>.*?)$~ism') as $result) {
			$property = strtolower($result['property']);
			$meta[$property] = trim($result['value']);

			switch ($property) {
				case 'tags':
					$meta[$property] = Strings::split($meta[$property], '~,\s+~', PREG_SPLIT_DELIM_CAPTURE);
					sort($meta[$property]);
					break;
				case 'date':
					$meta[$property] = new \DateTimeImmutable($meta[$property]);
					break;
			}
		}

		return $meta;
	}

}
