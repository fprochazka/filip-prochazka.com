<?php

declare(strict_types = 1);

namespace Fp\Sitemap;

use Fp\Blog\BlogIndex;
use Kdyby\StrictObjects\Scream;
use Nette\Application\LinkGenerator;

class SitemapIndex
{
	use Scream;

	/** @var string */
	private $appDir;

	/** @var \Fp\Blog\BlogIndex */
	private $blogIndex;

	/** @var \Nette\Application\LinkGenerator */
	private $linkGenerator;

	public function __construct(
		string $appDir,
		BlogIndex $blogIndex,
		LinkGenerator $linkGenerator
	)
	{
		$this->appDir = $appDir;
		$this->blogIndex = $blogIndex;
		$this->linkGenerator = $linkGenerator;
	}

	/**
	 * @return SitemapItem[]
	 */
	public function listPages(): array
	{
		$items = [];

		foreach (glob($this->appDir . '/templates/Static/*.latte') as $staticPageTemplate) {
			$pageName = basename($staticPageTemplate, '.latte');
			$items[] = new SitemapItem(
				$this->linkGenerator->link(sprintf('Static:%s', $pageName)),
				\DateTimeImmutable::createFromFormat('U', (string) filectime($staticPageTemplate)),
				SitemapItem::CHANGES_MONTHLY,
				($pageName === 'talks') ? '0.1' : '1.0'
			);
		}

		foreach (glob($this->appDir . '/templates/Static/talks/*.latte') as $talkPageTemplate) {
			$talkName = basename($talkPageTemplate, '.latte');
			$items[] = new SitemapItem(
				$this->linkGenerator->link('Static:talk', ['talk' => $talkName]),
				\DateTimeImmutable::createFromFormat('U', (string) filectime($talkPageTemplate)),
				SitemapItem::CHANGES_MONTHLY,
				'1.0'
			);
		}

		$items[] = new SitemapItem(
			$this->linkGenerator->link('Blog:default'),
			new \DateTimeImmutable(),
			SitemapItem::CHANGES_ALWAYS,
			'0.1'
		);

		$items[] = new SitemapItem(
			$this->linkGenerator->link('Blog:archive'),
			new \DateTimeImmutable(),
			SitemapItem::CHANGES_DAILY,
			'0.1'
		);

		foreach ($this->blogIndex->getCompleteIndex() as $blogIndexEntry) {
			$items[] = new SitemapItem(
				$this->linkGenerator->link('Blog:article', ['slug' => $blogIndexEntry->slug]),
				$blogIndexEntry->publishedTime,
				SitemapItem::CHANGES_MONTHLY,
				'1.0'
			);
		}

		return $items;
	}

}
