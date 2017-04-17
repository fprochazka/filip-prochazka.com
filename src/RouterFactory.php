<?php

namespace Fp;

use Kdyby\StrictObjects\Scream;
use Nette;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use Nette\Http;
use Nextras\Routing\StaticRouter;

class RouterFactory
{
	use Scream;

	/** @var bool */
	private $productionMode;

	public function __construct($productionMode)
	{
		$this->productionMode = (bool) $productionMode;
	}

	public function createRouter(): Nette\Application\IRouter
	{
		$router = new RouteList();

		$router[] = new StaticRouter([
			'Static:job' => 'job',
			'Static:cv' => 'cv',
			'Static:openSource' => 'open-source',
			'Static:donation' => 'donation',
			'Static:paidSupport' => 'paid-support',
			'Static:talks' => 'talks',
			'Blog:archive' => 'archive',
			'Sitemap:default' => 'sitemap.xml',
			'Blog:rss' => 'blog.rss',
			'Blog:default' => 'blog',
		]);

		$router[] = new Route('talks/<talk>', 'Static:talk');
		$router[] = new Route('blog/tag/<tag>.rss', 'Blog:tagRss');
		$router[] = new Route('blog/tag/<tag>', 'Blog:tag');
		$router[] = new Route('blog/<slug>', 'Blog:article');
		$router[] = new Route('/feeds/posts/default', [
			'presenter' => 'Blog',
			'action' => 'default',
			'rss' => 'rss'
		], Route::ONE_WAY);

		$router[] = new Route('/memes/<path .*>', function (string $path) {
			return new RedirectResponse('http://hosiplan.com/memes/' . str_replace('%2F', '/', urlencode($path)));
		});

		$router[] = new Route('/content/adminer.tar.gz', function () {
			return new RedirectResponse(
				'https://github.com/fprochazka/adminer-colors/archive/master.tar.gz',
				Http\IResponse::S301_MOVED_PERMANENTLY
			);
		});

		$router[] = new Route('tag/<tag>.rss', 'Blog:tagRss', Route::ONE_WAY);
		$router[] = new Route('/search/label/<tag>', ['presenter' => 'Blog', 'action' => 'tag'], Route::ONE_WAY);
		$router[] = new Route('/components', 'Static:openSource', Route::ONE_WAY);

		$router[] = new Route('<presenter>[/<action>]', 'Static:profile');

		return $router;
	}

}
