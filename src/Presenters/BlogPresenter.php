<?php

namespace Fp\Presenters;

use Fp\Blog\BlogIndex;
use Fp\Blog\EntryBySlugNotFoundException;
use Nette\Application\BadRequestException;
use Nette\Http\Url;

final class BlogPresenter extends BasePresenter
{

	/** @var \Fp\Blog\BlogIndex */
	private $blogIndex;

	public function __construct(BlogIndex $blogIndex)
	{
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
	}

	public function actionArchive()
	{
		$this->template->articles = $this->blogIndex->getCompleteIndex();
	}

}
