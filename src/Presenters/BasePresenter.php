<?php

declare(strict_types = 1);

namespace Fp\Presenters;

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

		$this->template->googleAnalyticsAccount = $this->googleAnalyticsAccount;
		$this->template->faviconMetas = $this->faviconsLoader->getMetadata();
	}

	protected function createTemplate()
	{
		/** @var \Nette\Bridges\ApplicationLatte\Template $template */
		$template = parent::createTemplate();
		$template->getLatte()->addFilter('filectime', 'filectime');
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
