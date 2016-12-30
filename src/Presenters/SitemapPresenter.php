<?php

namespace Fp\Presenters;

use Fp\Sitemap\SitemapIndex;

class SitemapPresenter extends BasePresenter
{

	/** @var \Fp\Sitemap\SitemapIndex */
	private $sitemapIndex;

	public function __construct(SitemapIndex $sitemapIndex)
	{
		parent::__construct();
		$this->sitemapIndex = $sitemapIndex;
	}

	public function renderDefault()
	{
		$this->template->sitemapIndex = $this->sitemapIndex;
	}

}
