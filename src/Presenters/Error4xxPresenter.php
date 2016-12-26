<?php

namespace Fp\Presenters;

use Nette;


class Error4xxPresenter extends BasePresenter
{

	public function startup()
	{
		parent::startup();
		if (!$this->getRequest()->isMethod(Nette\Application\Request::FORWARD)) {
			$this->error();
		}
	}

	public function renderDefault(Nette\Application\BadRequestException $exception)
	{
		// load template 403.latte or 404.latte or ... 4xx.latte
		$file = $this->appDir . "/templates/Error/{$exception->getCode()}.latte";
		$this->template->setFile(is_file($file) ? $file : $this->appDir . '/templates/Error/4xx.latte');
	}

}
