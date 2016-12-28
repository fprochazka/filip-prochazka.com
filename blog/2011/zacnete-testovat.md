Date: 2011-09-01
Tags: Testování

# Začněte testovat


Už je to pár měsíců, co jsem začal testovat a přišel jsem za tu dobu na pár věcí. Především, **psát testy se vyplatí**. Začal jsem sice poněkud zmateně, ale to dnes snad napravím. Článek je souhrnem poznatků z různých koutků testování a pevně věřím, že Vás nadchne pro jejich další studium.


## Instalace konfigurace a konvence

PHPUnit doporučuji nainstalovat pomocí [Pearu](http://pear.php.net/). Osobně používám 3.6.0RC4 a není s ní problém.

```shell
$ pear config-set auto_discover 1
# $ pear install --alldeps --force pear.phpunit.de/PHPUnit-3.6.0RC4 nainstaluje i Symfony YAML reader a pár dalších malých knihovniček
```

Mám konvenci, že v projektu je složka `libs/` a `tests/`. Když vytvářím nějaký test, tak ho umístím přesně do stejné složky, jako je v libs a suffixnu "Test", takže třeba `tests/Kdyby/Application/PresenterFactoryTest.php` a namespace testu je pak `Kdyby\Testing\Application`. Tato část je velice individuální pro spoustu lidí.

Ve složce s testy je potřebný config a boostrap. Config se jmenuje `phpunit.xml` a když napíšu ve složce s testy do příkazové řádky `$ phpunit` , tak si ho PHPUnit automaticky načte. Mně v současné době vyhovuje [tato velice standardní konfigurace](https://github.com/Kdyby/Framework/blob/master/phpunit.xml.dist). Naprostým základem konfigurace, je zapnutí barviček. Co si budeme nalhávat, koho by to bez té zelené bavilo?

Krásně se to používá, když člověk chce pouštět jeden test pořád dokola, aby ho nezdržovaly ostatní testy. Stačí se přesunout do složky s testy a zavolat

```shell
$ phpunit Kdyby/Application/PresenterFactoryTest.php
```

Viděl jsem totiž, že někteří načítají boostrap v každém jednom testu, což je zbytečné a já jsem to taky tak kdysi dělal. Mnohem jednodušší a spolehlivější je pouštět testy z jedné složky `tests/`, kde je konfigurace a je v ní napsané, kde je boostrap soubor.

Tím se dostáváme k `boostrap.php`, což je soubor, ve kterém je nutné nastavit autoloading tříd, popř. provést základní konfiguraci prostředí. Spustí se před začátkem testů a musí se zkratká postarat, aby jim nic nechybělo.

Tyto dva soubory si můžeme nastavit do IDE a pouštět testy z něj. V NetBeans toto nastavení vypadá například takto:

[![netbeans-phpunit-setup](https://dl.dropbox.com/u/32120652/netbeans-phpunit-setup.png)](https://dl.dropbox.com/u/32120652/netbeans-phpunit-setup.png)

A průběh testů vypadá takto:

[![netbeans-phpunit-runing](https://dl.dropbox.com/u/32120652/netbeans-phpunit-runing.png)](https://dl.dropbox.com/u/32120652/netbeans-phpunit-runing.png)


## Hello world test

Máme nainstalovaný a nastavený PHPUnit a můžeme napsat první test.

```php
class MyHelloWorldTest extends PHPUnit_Framework_TestCase
{
	public function testOneEqualsOne()
	{
		$this->assertTrue(1 == 1);
	}

	public function testHelloEqualsHello()
	{
		$this->assertTrue("hello" == "hello");
	}
}
```

Všimněte si hlavně pojmenování. Názvy třídy i metod jsou jako věta a popisují to, co se v testu děje a k čemu se vztahuje. V testech pak voláme tzv. "asserty". Vyjadřují podmínku, jakou jejich argumenty musí splnit. PHPUnit z toho pak generuje přehled a řekne nám, když některé testy neprojdou a proč neprošly. Je to takový chytřejší automatický `dump()` se statistikami.

[Assertů je celá řada](http://www.phpunit.de/manual/3.6/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.assertions) a doporučuji si je projít všechny. Oficiální dokumentace obsahuje pěkné a názorné ukázky.

Test si tedy uložíme, třeba do souboru `tests/Kdyby/MyHelloWorldTest.php` a spustíme

[![phpunit-green](https://dl.dropbox.com/u/32120652/phpunit-green.png)](https://dl.dropbox.com/u/32120652/phpunit-green.png)

Krásná zelená, testy fungují a můžeme začít vyvíjet!


## Chytřejší unit testy

PHPUnit nabízí možnost, překrýt si, mimo jiné, metody `setup` a `teardown`. Tyhle se opakovaně volají před každým zavoláním testovací metody a po každém zavolání testovací metody.

Ale pozor, je tu jeden chyták. PHPUnit vytváří pro každé zavolání testovací metody nový objekt testu. Není proto možné sdílet nějakou proměnnou mezi dvěma testy. Ovšem občas je to potřeba a na to se používá annotace `@depends`. Krásně to jde pochopit z [ukázky v dokumentaci](http://www.phpunit.de/manual/3.6/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.test-dependencies).

Dalším šikovným nástrojem jsou "zdroje dat". Často je potřeba pouštět ten samý test pro více různých vstupů a výstupů a bylo by velice otravné vypisovat jednotlivé asserty jen s různými proměnnými. Opět je to velice pěkně ukázané na [příkladu v dokumentaci](http://www.phpunit.de/manual/3.6/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers).

Co je asi nejdůležitější a velice opomíjená věc, je testovat chybové stavy. Je **velice důležité** mít otestované, že se třída nebude chovat nepředvídatelně v neočekávaných stavech, ale že třeba vyhodí výjimku. Na to se hodí [testování výjimek](http://www.phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.exceptions).


## Testování databáze

Tohle je perlička sama o sobě. PHPUnit nabízí brášku jménem DbUnit, který se tváří jako solidní základ pro testování databáze. Když pominu fakt, že není kompatibilní s posledním PHPUnitem, ale "jen" s 3.5, tak je to celkem použitelný nástroj. Má to ovšem několik ale, které jsem já nepřekousl:

- Zabere si metody `setup` a `teardown`, které když chcete použít, nesmíte zapomenout volat předka `parent::setup()` a `parent::teardown()` a nijak nás neupozorní, když zapomeneme (upozorní nás až nefunkční test a nesouvisející hlášky)
- DataSet je jakýsi objekt, do kterého se vkládají další objekty, pro jednotlivé tabulky, které obsahují jednotlivé řádky tabulek. Tyto DataSety se pak porovnávají. Nejenom, že mají opravdu hloupé API, ale dokonce mají i otřesně řešené asserty. Představte si, že máme tabulku se stovkami záznamů a testujeme dva DataSety. PHPUnit nám správně řekne, že se třeba nerovnají, ale zároveň do toho pomocí "ASCII grafiky" vypíše jednotlivé záznamy a to tak, že úplně všechny. Běžný smrtelník nemá šanci na prví pohled najít rozdíl a opravit tak kód.
- Maže a vytváří databázi úplně pokaždé. Tento bod je technicky vzato správně. Jak jinak docílit dokonalou izolovanost testů, než že se pro každý test vytvoří znovu čisté schéma. Ovšem je tu problém s výkonem, taková operace je logicky tím náročnější, čím více máte tabulek a tím pomalejší. Jako bonus zahazuje spojení s databází a vytváří nové, před úplně každým testem, i když v něm nejsou operace s databází.

Používám na práci s databází Doctrine 2. Jedno jeho rozšíření obsahuje vrstvičku nad DbUnitem, která má za úkol obnovovat databázi a integrovat tyto dva nástroje do sebe. Ani tento však není pro mě dostatečně použitelný. Je to tak na hraní a pochopení, co je kde potřeba pohlídat.

Od DbUnit jsem tedy upustil a vymyslel si vlastní udělátko. Vysvětlím pouze obecný princip, koho by zajímaly detaily, najde je v [mém repozitáři na githubu](https://github.com/Kdyby/Framework/tree/master/libs/Kdyby/Testing).

Mám poděděný `PHPUnit_Framework_TestCase` a v něm, ve statické vlastnosti, instanci třídy `MemoryDatabaseManager`. Tato třída umí na požádání vytvořit nakonfigurované objekty, které potřebuji k práci s databází, u Doctrine je to `EntityManager` a jeho závislosti. Byť tady porušuji princip izolovanosti, na svou obranu musím říct, že tím získám obrovské zvýšení výkonu hned z několika důvodu.

Celé je to lazy. V momentě prvního požadavku o `EntityManager`, se vytvoří připojení na SQLite Memory (tento typ databáze na testování doporučuje i PHPUnit v dokumentaci) a dalším krokem je vytvoření schématu databáze. Díky tomu, že si připojení držím staticky, můžu ho recyklovat a vždy jen vyprázdním databázi před dalším testem. Princip izolovanosti tedy porušuji jenom "tak trošku" a získám tím ohromné zvýšení výkonu.

Menší nevýhoda tohoto přístupu je, že si musím psát vlastní assert metody, pokud chci testovat přímo databázi nebo nějaké výsledky operací.


## Vývoj řízený testy

"TDD"((Test Driven Development)) říká, že první jsou testy a pak až implementace. Když totiž programátor napíše nejdříve kód, který bude konečnou implementaci používat, tak dovede třídu navrhnout mnohdy lépe, než kdyby strávil hodiny nad papírem, nebo nějakým class diagramem.

TDD také definuje iteraci "red, green, refactor". Ve zkratce to znamená, že se napíše test a ten se spustí. Testovací nástroj na nás bude křičet červeně, protože test neprojde, nebyl totiž implementován. Dalším krokem je jeho implementace. Napíšeme nezbytné minimum kódu pro to, aby test fungoval. Když se nám objeví zelená, tak refaktorujeme. Zamyslíme se, co by šlo udělat lépe a implementaci měníme k dokonalosti v nekonečné smyčce "red, green, refactor".

Osobně mám s tímto přístupem problém. Možná se málo snažím, možná jsem ze staré školy, ale psát prvně testy se asi jen tak nenaučím. Pro začátek mi stačí, že mám třídy pokryté testy, i když byly napsány až po implementaci.
