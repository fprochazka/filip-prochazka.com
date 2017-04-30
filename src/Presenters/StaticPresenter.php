<?php

namespace Fp\Presenters;

class StaticPresenter extends BasePresenter
{

	public function actionTalk(string $talk)
	{
		$this->setView('talks/' . $talk);
	}

	protected function beforeRender()
	{
		parent::beforeRender();

		$this->template->wkhtmltopdf = stripos($this->getHttpRequest()->getHeader('User-Agent', ''), 'wkhtmltopdf') !== false;
	}

}
