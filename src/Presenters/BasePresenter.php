<?php

declare(strict_types = 1);

namespace Fp\Presenters;

use Fp\Template\TemplateHelpers;
use Nette\Application\UI\Presenter;
use Nette\Application\Helpers;

abstract class BasePresenter extends Presenter
{

	/** @var string */
	public $appDir;

	/** @var string */
	public $wwwDir;

	/** @var bool */
	public $productionMode;

	/** @var string */
	public $googleAnalyticsAccount;

	/** @var string */
	public $disqusShortname;

	/** @var string */
	public $twitterHandle;

	/** @var string */
	public $gplusAccountId;

	/** @var string */
	public $facebookUsername;

	/** @var string */
	public $facebookProfileId;

	/** @var \Fp\FaviconsLoader @inject */
	public $faviconsLoader;

	protected function startup()
	{
		parent::startup();
		if ($this->appDir === null) {
			throw new \Exception('%appDir% was not provided');
		}
	}

	protected function beforeRender()
	{
		parent::beforeRender();

		$this->template->wwwDir = $this->wwwDir;
		$this->template->productionMode = $this->productionMode;

		$this->template->faviconMetas = $this->faviconsLoader->getMetadata();

		$this->template->disqusShortname = $this->disqusShortname;
		$this->template->twitterHandle = $this->twitterHandle;
		$this->template->gplusAccountId = $this->gplusAccountId;
		$this->template->facebookUsername = $this->facebookUsername;
		$this->template->facebookProfileId = $this->facebookProfileId;

		$this->template->now = new \DateTimeImmutable();
	}

	protected function createTemplate()
	{
		/** @var \Nette\Bridges\ApplicationLatte\Template $template */
		$template = parent::createTemplate();
		$template->getLatte()->addFilter('filectime', 'filectime');
		$template->getLatte()->addFilter('timeAgo', TemplateHelpers::class . '::timeAgoInWords');
		return $template;
	}

	/**
	 * Formats layout template file names.
	 *
	 * @return string[]
	 */
	public function formatLayoutTemplateFiles(): array
	{
		if (is_string($this->layout) && preg_match('#/|\\\\#', $this->layout)) {
			return [$this->layout];
		}

		return [
			sprintf('%s/templates/@%s.latte', $this->appDir, $this->layout ?: 'layout'),
		];
	}

	/**
	 * Formats view template file names.
	 *
	 * @return string[]
	 */
	public function formatTemplateFiles(): array
	{
		list(, $presenter) = Helpers::splitName($this->getName());

		return [
			sprintf('%s/templates/%s/%s.latte', $this->appDir, $presenter, $this->view),
		];
	}

}
