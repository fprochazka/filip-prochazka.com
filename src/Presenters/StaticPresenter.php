<?php

namespace Fp\Presenters;

class StaticPresenter extends BasePresenter
{

	/** @var string */
	private $paypalKeyFile;

	public function __construct(string $paypalKeyFile)
	{
		parent::__construct();

		$this->paypalKeyFile = $paypalKeyFile;
	}

	public function actionTalk(string $talk)
	{
		$this->setView('talks/' . $talk);
	}

	public function renderDonation()
	{
		$this->template->paypalKey = file_get_contents($this->paypalKeyFile);
	}

	protected function beforeRender()
	{
		parent::beforeRender();

		$this->template->wkhtmltopdf = stripos($this->getHttpRequest()->getHeader('User-Agent', ''), 'wkhtmltopdf') !== false;
	}

}
