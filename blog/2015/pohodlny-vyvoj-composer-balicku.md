Date: 2015-07-19 13:45
Tags: Composer, PHP, Git

# Pohodlný vývoj Composer balíčků

[Na fóru se Lexi ptal](http://forum.nette.org/cs/23721-jak-na-vlastni-composer-balicek-s-nette-komponentou#p159274),
jak řešit vývoj Composer balíčků. Já vyzkoušel tyhle tři varianty, respektive tyhle 3 jsou podle mě použitelné a hodí se v různých situacích.


## Commitování z vendor složky

Nejspíš nemáte ve `vendor/` složce ten balíček co chcete vyvíjet vůbec jako repozitář,
takže nejprve celou složku `vendor/` smažeme a nainstalujeme závislosti znovu.

```shell
composer update --prefer-source
```

Při dalším update, v případě přidávání nových závislostí, je pak potřeba myslet buď na použití přepínače `--prefer-source`,
který závislosti zkouší klonovat jako repozitáře, nebo si to můžete vynutit v [globálním composer configu](https://getcomposer.org/doc/03-cli.md#global).

V tenhle moment je možné si přidat balíčky ve vendor jako vcs repozitáře do PhpStormu,
díky kterému pak můžeme některé (nebo všechny) operace dělat přímo z IDE.

Je ale potřeba myslet na to, že pokud používáte Tasks v PhpStormu, tak ten dareba vždy vytvoří novou branch úplně ve všech registrovaných repozitářích projektu.
Sice jen lokálně, ale i tak v tom je zachvilku docela bordel. Tohle zatím neumím obejít.

Tenhle přístup jsem zkoušel použít jen jednou a je zdaleka nejjednodušší na rychlé a malé změny.
Hlavní nevýhoda je v tom, že když máte hodně lokálních projektů s nainstalovanou tou knihovnou co spravujete,
tak máte pak taky hodně míst, ve kterých musíte udržovat svoje změny a synchronizovat je.


## Symlinky

Já mám všechny [Kdyby balíčky](https://github.com/kdyby) v jedné složce. Vždy když vytvářím nový, nebo mezi nimi přidávám závislost, tak po `composer update` vlezu do jejich vendor složky
a ty nový věci z `vendor/` smažu a [udělám místo nich symlinky](https://cs.wikipedia.org/wiki/Symbolick%C3%BD_odkaz) na tu "hlavní" složku v systému.
Když pak volám `composer update` nebo něco commitnu, tak to mám v úplně všech projektech, kde je balíček nalinkovaný.

Podle mě je tohle asi nejlepší řešešní, protože stejně chci všechny projekty na localhostu testovat a vyvíjet s posledním commitem svých balíčků v masteru.
Třeba Rohlik.cz jede celej na `dev-master` mých balíčků.

Je potřeba ale nezapomenout na jednu drobnost. Composer totiž, když si instaluje balíček jako source (git repo),
tak mu vytvoří remote s názvem `composer` a přes něj fetchuje a pulluje změny a tagy.
Například kdybych si udělal symlink na [Kdyby/Doctrine](https://github.com/Kdyby/Doctrine) do projektu,
tak si pak v té knihovně (né v projektu, v knihovně kterou symlinkuju) přidám remote pro Composer.
Samozřejmě to stačí udělat jednou pro každou knihovnu.

```shell
$ git remote add composer https://github.com/Kdyby/Doctrine.git
```

Tohle řešení má snad jedinou nevýhodu, a to když mám projekty, které závisí na vzájemně nekompatibilních verzích mých balíčků.
Například Rohlik.cz mám na Nette 2.3, ale nějakej archaickej projekt na 2.1. Tenhle problém řeší následující přístup.


## Více pracovních složek

Díky tomu jak git funguje, tak je možné vytvořit novou workdir, která ale bude sdílet historii s jinou.
Dělá se to [pomocí příkazu `git-new-workdir`](http://stackoverflow.com/a/6270727/602899),
který ovšem není běžně dostupný jako ostatní příkazy a je potřeba si ho buď doinstalovat nebo nalinkovat do systémového `PATH`.

Například mám v Kdyby/Doctrine branch `nette-2.1` a potřebuji ji pro projekty s Nette 2.1. Takže vlezu do `vendor/kdyby`, smažu složku `doctrine` a spustím

```shell
git-new-workdir ~/develop/libs/kdyby/components/doctrine doctrine nette-2.1
```

A teď mám stejnou history a všechny branche a tagy dostupné ve všech workdirs.
Sice v projektech musíme `HEAD` posouvat ručně, ale nemusíme už složitě synchronizovat commity mezi složkami.

Tenhle přístup používám pouze pokud opravdu potřebuju v nějakém projektu starší závislost, raději použvám symlinky.
