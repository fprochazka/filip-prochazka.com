<?php

declare(strict_types = 1);

namespace Fp\Blog;

use Fp\Blog\Markdown\CustomMarkdown;
use Kdyby\StrictObjects\Scream;
use Nette\Utils\FileSystem;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Nette\Utils\Strings;

class BlogIndex
{
	use Scream;

	/** @var string */
	private $blogDir;

	/** @var \Fp\Blog\Markdown\CustomMarkdown */
	private $markdown;

	/** @var string */
	private $cacheDir;

	public function __construct(string $blogDir, string $contentCacheDir, CustomMarkdown $markdown)
	{
		$this->blogDir = $blogDir;
		$this->markdown = $markdown;
		$this->cacheDir = $contentCacheDir;
	}

	/**
	 * @return BlogIndexEntry[]
	 */
	public function getFirstN(int $itemsCount)
	{
		return array_slice($this->getCompleteIndex(), 0, $itemsCount, true);
	}

	public function getWithSlug(string $slug): BlogIndexEntry
	{
		$index = $this->getCompleteIndex();
		if (!array_key_exists($slug, $index)) {
			throw new \Fp\Blog\EntryBySlugNotFoundException($slug);
		}

		return $index[$slug];
	}

	public function getWithTag(string $tag): BlogIndexListWithTagResult
	{
		$results = array_filter(
			$this->getCompleteIndex(),
			function (BlogIndexEntry $entry) use ($tag): bool {
				return array_key_exists($tag, $entry->tags);
			}
		);

		if (count($results) === 0) {
			throw new EntriesByTagNotFoundException($tag);
		}

		return new BlogIndexListWithTagResult(
			$results,
			reset($results)->tags[$tag] ?? $tag
		);
	}

	/**
	 * @return BlogIndexEntry[]
	 */
	public function getCompleteIndex(): array
	{
		if (file_exists($this->getIndexFilename())) {
			return $this->load();
		}

		$index = $this->createIndex();
		$this->save($index);

		return $index;
	}

	public function getEntryHtmlContent(BlogIndexEntry $indexEntry): Html
	{
		return Html::el()->setHtml(file_get_contents($this->cacheDir . '/' . $indexEntry->getContentTempFile()));
	}

	private function sortArticles(array $index): array
	{
		uasort(
			$index,
			function (BlogIndexEntry $a, BlogIndexEntry $b): int {
				return -1 * ($a->publishedTime <=> $b->publishedTime);
			}
		);

		return $index;
	}

	/**
	 * @return BlogIndexEntry[]
	 */
	private function createIndex(): array
	{
		FileSystem::createDir($this->cacheDir);

		$index = [];

		foreach (glob($this->blogDir . '/*') as $yearDir) {
			foreach (glob($yearDir . '/*.md') as $articleFile) {
				$processed = $this->markdown->parse(file_get_contents($articleFile));
				$slug = basename($articleFile, '.md');

				if (!isset($processed->metadata['date'])) {
					throw new \Exception(sprintf('Missing publication date for %s', $slug));
				}

				if (basename($yearDir) !== $processed->metadata['date']->format('Y')) {
					throw new \Exception(sprintf('Article %s is in wrong directory', $slug));
				}

				if (array_key_exists($slug, $index)) {
					throw new \Exception(sprintf('Duplicate slug %s', $slug));
				}

				$tags = [];
				foreach ($processed->metadata['tags'] ?? [] as $tagTitle) {
					$tags[Strings::webalize($tagTitle)] = $tagTitle;
				}

				$entry = new BlogIndexEntry(
					basename($yearDir) . '/' . basename($articleFile),
					$processed->title,
					$slug,
					$processed->metadata['titleLink'] ?? null,
					$processed->description,
					$processed->htmlIntro,
					$processed->metadata['date'],
					$tags,
					$processed->images ?? []
				);

				file_put_contents($this->cacheDir . '/' . $entry->getContentTempFile(), $processed->htmlContent);

				$index[$entry->slug] = $entry;
			}
		}

		return $this->sortArticles($index);
	}

	private function save(array $index)
	{
		file_put_contents(
			$this->getIndexFilename(),
			Json::encode(
				(object) $index,
				Json::PRETTY
			)
		);
	}

	private function load(): array
	{
		return $this->sortArticles(array_map(
			function (array $entry): BlogIndexEntry {
				return BlogIndexEntry::createFromArray($entry);
			},
			Json::decode(
				file_get_contents($this->getIndexFilename()),
				Json::FORCE_ARRAY
			)
		));
	}

	private function getIndexFilename(): string
	{
		return $this->cacheDir . '/index.json';
	}

}
