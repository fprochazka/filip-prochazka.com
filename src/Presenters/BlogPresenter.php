<?php

namespace Fp\Presenters;

use Fp\Blog\BlogIndex;
use Fp\Blog\BlogIndexEntry;
use Fp\Blog\EntryBySlugNotFoundException;
use Fp\Blog\Rss\RssItem;
use Nette\Application\BadRequestException;
use Nette\Http\Url;

final class BlogPresenter extends BasePresenter
{

	/** @var \Fp\Blog\BlogIndex */
	private $blogIndex;

	public function __construct(BlogIndex $blogIndex)
	{
		parent::__construct();
		$this->blogIndex = $blogIndex;
	}

	public function actionDefault()
	{
		$this->template->articles = $this->blogIndex->getFirstN(5);
	}

	public function actionArticle(string $slug)
	{
		try {
			$article = $this->blogIndex->getWithSlug($slug);
			$this->template->article = $article;
			$this->template->articleContent = $this->blogIndex->getEntryHtmlContent($article);

		} catch (EntryBySlugNotFoundException $e) {
			throw new BadRequestException($e->getMessage(), 0, $e);
		}

		$this->template->facebookShareLink = (new Url('https://www.facebook.com/sharer.php'))->setQuery([
			'u' => $this->link('//Blog:article', $article->slug),
			't' => $article->title,
		]);
		$this->template->gplusShareLink = (new Url('https://plus.google.com/share'))->setQuery([
			'url' => $this->link('//Blog:article', $article->slug),
		]);
		$this->template->tweetShareLink = (new Url('https://twitter.com/intent/tweet'))->setQuery([
			'text' => sprintf('%s %s via @%s',
				$article->title,
				$this->link('//Blog:article', $article->slug),
				$this->twitterHandle
			),
		]);
	}

	public function actionTag(string $tag)
	{
		$result = $this->blogIndex->getWithTag($tag);
		$this->template->articles = $result->results;
		$this->template->tagTitle = $result->tagTitle;
		$this->template->tagSlug = $tag;
	}

	public function actionArchive()
	{
		$this->template->articles = $this->blogIndex->getCompleteIndex();
	}

	public function actionRss()
	{
		$this->template->items = $this->articlesToRssItems($this->blogIndex->getCompleteIndex());
		$this->template->link = $this->link('//Blog:');
		$this->template->rssDateFormat = \DateTime::RSS;
		$this->template->description = 'Co se nevešlo na Twitter';
		$this->setView('_rss');
	}

	public function actionTagRss(string $tag)
	{
		$result = $this->blogIndex->getWithTag($tag);
		$this->template->items = $this->articlesToRssItems($result->results);
		$this->template->link = $this->link('//Blog:tag', $tag);
		$this->template->rssDateFormat = \DateTime::RSS;
		$this->template->description = sprintf('Co se nevešlo na Twitter - %s', $result->tagTitle);
		$this->setView('_rss');
	}

	/**
	 * @param BlogIndexEntry[] $index
	 * @return RssItem[]
	 */
	private function articlesToRssItems(array $index): array
	{
		$items = [];
		foreach ($index as $indexEntry) {
			$items[] = new RssItem(
				$indexEntry->title,
				$this->link('//article', [
					'slug' => $indexEntry->slug,
					'utm_source' => 'rss',
					'utm_medium' => 'feed',
					'utm_campaign' => $indexEntry->title,
				]),
				$indexEntry->publishedTime,
				$this->blogIndex->getEntryHtmlContent($indexEntry),
				$this->link('//article', $indexEntry->slug)
			);
		}

		return $items;
	}

}
