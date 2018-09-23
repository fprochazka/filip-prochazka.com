---
layout: blogpost
title: "Barvičky v RSS"
permalink: blog/barvicky-v-rss
date: 2012-09-17 21:30
tag: ["PHP", "FSHL"]
---

Chcete mít krásný výstup v RSS čtečkách? Já taky!

FSHL generuje do "zvýrazněného" kódu "pouze" css třídy.
To má zjevné výhody - stylovat můžete z jednoho místa, v CSS souboru, a ve výsledném kódu pak není zbytečný bordel.
Takový obarvený kód je pak na webu krásný, ale v RSS čtečkách už to taková sláva není, protože ty neví co znamenají naše CSS třídy.

Nejprve mě napadlo zkusit posílat i CSS styly, vždy na konci článku.
Jenže lenost testovat, jestli to funguje, zvítězila a raději třídy nahrazuji přímo inline stylem.

"Do své entity, která mi představuje článek"((já vím, že to není ideální, ale v systému tohoto blogu je to good-enought místo)), jsem si tedy přidal metodu, která příjme cestu k CSS souboru a všechny CSS třídy z HTML kódu nahradí jejich stylem z předaného souboru.

~~~ php
/** @var array */
private static $languages = array(
	'php', 'neon', 'config', 'sh', 'texy', 'js', 'css', 'sql', 'html'
);

/**
 * @param string $cssFile
 * @return string
 */
public function getRssContent($cssFile = NULL)
{
	if (!$cssFile) {
		return $this->htmlContent;
	}

	$cssDefs = file_get_contents($cssFile);
	$langs = self::$languages;
	return Strings::replace($this->htmlContent, '~class=(?:"|\')?([^"\'>]+)(?:"|\')?~i', function ($class) use ($cssDefs, $langs) {
		$style = NULL;
		foreach (Strings::split($class[1], '~\s+~') as $class) { // jednotlivé třídy
			if (count($parts = explode('-', $class, 2)) !== 2 || !in_array($parts[0], $langs)) {
				// pokud třída není ve tvaru "<jazyk>-<klíčové slovo>", tak přeskoč
				// pokud jazyk není ve slovníku, tak přeskoč
				continue;
			}

			if ($css = Strings::match($cssDefs, '~.' . preg_quote($class) . '\s*\{([^}]*?)\}~')) {
				// nahrazení stylem ze souboru
				$style .= Strings::replace($css[1], array('~[\n\r]+~' => '')) . ';';
			}
		}

		return $style ? 'style="' . htmlspecialchars($style, ENT_QUOTES) . '"' : NULL;
	});
}
~~~


A jak obarvujete kód ve svých RSS vy? :)
