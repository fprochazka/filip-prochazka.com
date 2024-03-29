---
layout: blogpost
title: "Nette/Tester a PhpStorm"
permalink: blog/nette-tester-a-phpstorm
date: 2013-01-19 18:00
tag: ["PhpStorm", "Nette Framework", "Nette Tester", "PHP"]
---

Rozhodl jsem se přepsat všechny svoje testy z PHPUnitu na [Nette\Tester](https://github.com/nette/tester) a po prvním týdnu mohu s klidným svědomím říct, že se přepisování velice daří a jsem s Testerem spokojený.

<!--more-->
## Vytvoření ekosystému

Abychom mohli testy spouštět, potřebujeme do projektu nainstalovat Nette\Tester a nakopírovat pár kraťoučkých scriptů. Instalovat doporučuji pomocí [Composeru](https://doc.nette.org/cs/composer)

~~~ js
    "require-dev": {
        "nette/tester": "@dev"
    },
~~~

Pomocí příznaku `--dev` nainstalujeme i "vývojové" závislosti (tedy `nette/tester`)

~~~ shell
$ composer update --dev
~~~


Potřebujeme testy spouštět, takže vytvoříme soubor `tests/run-tests.sh` (předpokládám linux) a nakopírujeme do něj [tento script](https://github.com/Kdyby/Redis/blob/master/tests/run-tests.sh). Pokud chcete spouštět testy i na Windows, potřebujete [takovýto .bat soubor](https://github.com/nette/nette/blob/master/tests/RunTests.bat).

Vytvořím si prázdný `tests/php.ini-unix`, nebo jeho alternativu pro Windows `tests/php.ini-win`. Do tohoto `.ini` souboru můžeme zapisovat specifické nastavení pro testovací prostředí. Například inicializaci memcache, nebo jiných php rozšíření.

S oblibou používám i [script na php lint](https://github.com/Kdyby/Redis/blob/master/tests/lint.php), který zkontroluje syntaxi všech php scriptů, [předtím, než spustí testy](https://github.com/Kdyby/Redis/blob/master/.travis.yml#L13).

Testy v [Travisu](https://travis-ci.org) spouštím jednoduše `$ ./tests/run-tests.sh -s tests/KdybyTests/`, viz [konfigurace](https://github.com/Kdyby/Redis/blob/master/.travis.yml).


## Jednotlivé testy

Každý test potřebuje základní `bootstrap.php` soubor, [podobný tomuto](https://github.com/Kdyby/Curl/blob/master/tests/KdybyTests/bootstrap.php), který jsem si umístil do `tests/KdybyTests/bootstrap.php`. Obsahuje načtení autoloaderu, který vygeneroval Composer, zavěšení error/exception handlerů, unikátní temp složka (aby se testy mohly spouštět paralelně), vynulování proměnných prostředí, inicializace code coverage a pomocné funkce.

Každý test musí mít koncovku `.phpt` a [obsahuje načtení bootstrapu](https://github.com/Kdyby/Curl/blob/a84e648a1561d782684ac379cc6df13630c7befa/tests/Kdyby/Curl/CurlWrapper.phpt#L16), [hlavičku s metadaty o testu](https://github.com/Kdyby/Curl/blob/a84e648a1561d782684ac379cc6df13630c7befa/tests/Kdyby/Curl/CurlWrapper.phpt#L3) a samotné [spuštění testu](https://github.com/Kdyby/Curl/blob/a84e648a1561d782684ac379cc6df13630c7befa/tests/Kdyby/Curl/CurlWrapper.phpt#L112)

Jednotlivé testy dědí od `Tester\TestCase` a obsahují klasické `test` metody, jaké známe z PHPUnitu.

Asserty nevoláme nad testem, ale pomocí statické třídy `Tester\Assert`.


## Šablona pro rychlé vytváření testů

V PhpStormu jsem si nadefinoval šablonu, díky které je vytvoření nového testu otázka tří vteřin. Šablony jsou v "File > Settings > File Templates > záložka Templates". Šablonu pojmenujeme například "Nette test" a dáme jí koncovku `phpt`.


~~~ php
<?php

/**
 * Test: Kdyby\\${PACKAGE}\\${NAME}${DESCRIPTION}.
 *
 * @testCase Kdyby\\${PACKAGE}\\${NAME}Test
 * @author Filip Procházka <mr@fprochazka.cz>
 * @package Kdyby\\${PACKAGE}
 */

namespace KdybyTests\\${PACKAGE};

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <mr@fprochazka.cz>
 */
class ${NAME}Test extends Tester\TestCase
{

    public function setUp()
    {

    }

    public function test()
    {

    }

}

\run(new ${NAME}Test());
~~~


## Spouštěč testů

PhpStorm obsahuje podporu pro spouštění bashových scriptů (a nejspíš i `.bat`, ale to zjišťovat nebudu). Je nutné [nainstalovat plugin BashSupport](https://plugins.jetbrains.com/plugin/?id=4230), ideálně pomocí repozitáře v `Settings > Plugins > Browse repositories`.

Konfiguruje se velice snadno. Kliknu pravým tlačítkem ma `tests/run-tests.sh` a zvolím "Create 'run-tests'"

![phpstorm-tester-create-bashrunner](/content/phpstorm-tester-create-bashrunner.png)

a nastavím složku s testy a pracovní adresář.

![phpstorm-tester-runconfig-concrete](/content/phpstorm-tester-runconfig-concrete.png)

Testy spustím tak, že vyberu příslušný spouštěč a kliknu na zelené "Play"

![phpstorm-tester-runconfig](/content/phpstorm-tester-runconfig.png)

Průběh testů pak vypadá takto

![phpstorm-tester-result](/content/phpstorm-tester-result.png)

Není také problém, zvolit kterýkoliv test a jednoduše ho spustit. Každý test je díky bootstrapu a funkci `run()` na konci soběstačným scriptem, který je možné spouštět bez jakýchkoliv dalších závislostí.


## Závěrem

Nette/Tester samozřejmě ještě není dokonalý. Chybí mockovací nástroj (naštěstí si můžeme vybírat z několika jiných) a také má pár drobných mušek, co se týče použitelnosti. Kvalitativně je ovšem na úplně jiné úrovni, než přeplácaný a zbastlený PHPUnit a vřele ho doporučuji. Jako bonus je jeho používání v PhpStormu velice pohodlné.
