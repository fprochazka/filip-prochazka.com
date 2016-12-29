<?php

declare(strict_types = 1);

namespace Fp\Blog\Rss;

use Kdyby\StrictObjects\Scream;
use Nette\Utils\Strings;

class InlineCssHelper
{
	use Scream;

	/** @var string */
	private $cssFile;

	/** @var string[] */
	private static $languages = ['php', 'neon', 'config', 'sh', 'texy', 'js', 'css', 'sql', 'html'];

	public function __construct(string $cssFile)
	{
		$this->cssFile = $cssFile;
	}

	public function getHtmlWithInlinedStyles(string $html): string
	{
		$cssDefinitions = file_get_contents($this->cssFile);
		$whitelist = self::$languages;
		return Strings::replace(
			$html,
			'~class=(?:"|\')?([^"\'>]+)(?:"|\')?~i',
			function (array $matchedClass) use ($cssDefinitions, $whitelist) {
				$style = null;
				foreach (Strings::split($matchedClass[1], '~\s+~') as $class) {
					if (count($parts = explode('-', $class, 2)) !== 2 || !in_array($parts[0], $whitelist)) {
						continue;
					}
					if ($css = Strings::match($cssDefinitions, '~.' . preg_quote($class, '~') . '\s*\{([^}]*?)\}~')) {
						$style .= Strings::replace($css[1], ['~[\n\r]+~' => '']) . ';';
					}
				}

				return $style ? 'style="' . htmlspecialchars($style, ENT_QUOTES) . '"' : null;
			}
		);
	}

}
