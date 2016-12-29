<?php

declare(strict_types = 1);

namespace Fp\Blog\Markdown;

use Kdyby\StrictObjects\Scream;
use Latte\Runtime\FilterInfo;
use Latte\Runtime\Filters;
use Nette\Utils\Strings;

class CustomMarkdown
{

	use Scream;

	const HEADINGS_REGEXP = '~(\\<(h\\d)[^>]*\\>.*?\\<\\/\\2\\>)~is';

	public function parse(string $content): CustomMarkdownResult
	{
		$processor = new CustomMarkdownProcessor();

		$completeHtml = $processor->text($content);
		$htmlIntro = self::contentBetweenFirstTwoHeadings($completeHtml);

		return new CustomMarkdownResult(
			self::contentWithoutFirstHeading($completeHtml),
			$htmlIntro,
			self::descriptionFromHTML($htmlIntro, 350),
			$processor->headings[0][1], // title
			$processor->images,
			$processor->metadata
		);
	}

	private static function contentBetweenFirstTwoHeadings(string $html): string
	{
		return trim(Strings::split($html, self::HEADINGS_REGEXP)[3]);
	}

	private static function contentWithoutFirstHeading(string $html): string
	{
		return trim(preg_split(self::HEADINGS_REGEXP, $html, 2, PREG_SPLIT_DELIM_CAPTURE)[3]);
	}

	private static function descriptionFromHTML(string $html, int $length): string
	{
		return Filters::spacelessText(Strings::truncate(Filters::stripHtml(new FilterInfo('html'), $html), $length));
	}

}
