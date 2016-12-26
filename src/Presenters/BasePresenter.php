<?php

declare(strict_types = 1);

namespace Fp\Presenters;

use Nette\Application\UI\Presenter;
use Nette\Application\Helpers;

abstract class BasePresenter extends Presenter
{

	/** @var string */
	public $appDir;

	protected function startup()
	{
		parent::startup();
		if ($this->appDir === null) {
			throw new \Exception('%appDir% was not provided');
		}
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
