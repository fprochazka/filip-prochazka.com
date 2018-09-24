---
layout: blogpost
title: "Presentery v DI Containeru"
permalink: blog/presentery-v-di-containeru
date: 2014-11-15 19:15
tag: ["Nette Framework", "PHP"]
---

V Nette Frameworku funguje instanciování presenterů tak, že [v PresenterFactory](https://api.kdyby.org/class-Nette.Application.PresenterFactory.html#_createPresenter)
se nějak přeloží název presenteru v "Nette tvaru", třeba `Front:Homepage` na název třídy, třeba na `FrontModule\HomepagePresenter` (což závisí na konvenci a jde to samozřejmě změnit).

V tenhle moment známe název třídy, která se má instanciovat a spustit. Jenže tato třída má nějaké závislosti a je potřeba správně vytvořit instanci a tyto závislosti předat.
Jak na to? Dříve to fungovalo tak, že se prostě přes reflexi kouklo na konstruktor a proběhl [autowire](https://doc.nette.org/cs/2.2/configuring#toc-auto-wiring).

Před pár měsíci (nebo roky?) byla přidána podpora pro "vytahování" instancí presenterů [z DI Containeru](https://github.com/nette/application/blob/226c1f1deb00cfeb1c4e60bdb5eaa962775afd8e/src/Application/PresenterFactory.php#L51).
Což je strašně fajn, protože si pak můžete presentery zaregistrovat do DI Containeru.
Analýza závislostí se pak provede compile-time, tedy právě jednou a výkon i čas sežere také právě jednou.

<!--more-->
## Automatická registrace

Kdo by se ale vypisoval se seznamem presenterů, když je aplikace může najít sama a sama si je zaregistrovat!
Vytvářejí se pouze až když jsou potřeba a tedy nám i případně nepoužívané presentery vůbec nevadí v DI Containeru.

Navíc takto se při každé kompilaci aplikace načtou všechny presentery a DI Container je zkusí zkompilovat do služeb,
címž zároveň získáme kontrolu, že v nich není syntax error, nebo závislost na službě, která by třeba nebyla ani registrovaná v DI Containeru!

Pojďme si tedy napsat primitivní extension, který nám je všechny najde a zaregistruje, třeba takovýto:

~~~ php
class PresentersExtension extends Nette\DI\CompilerExtension
{
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        if ($builder->parameters['debugMode']) {
            return; // production only
        }

        // pokud by se vymyslela nějaká inteligentní invalidace
        // a cachování pro tento robotloader
        // tak se můžou presentery registrovat i v debug módu

        $robot = new Nette\Loaders\RobotLoader();
        $robot->addDirectory($builder->expand('%appDir%'));
        $robot->setCacheStorage(new Nette\Caching\Storages\MemoryStorage());
        $robot->rebuild();

        $counter = 0;
        foreach ($robot->getIndexedClasses() as $class => $file) {
            try {
                $refl = Nette\Reflection\ClassType::from($class);

                if (!$refl->implementsInterface(Nette\Application\IPresenter::class)) {
                    continue;
                }

                if (!$refl->isInstantiable()) {
                    continue;
                }

                $builder->addDefinition($this->prefix(++$counter))
                    ->setClass($class)
                    ->setInject(TRUE);

            } catch (\ReflectionException $e) {
                continue;
            }
        }
    }
}
~~~

Easy a teďka ji zaregistrujeme do DI Containeru

~~~ neon
extensions:
    presenters: My\PresentersExtension
~~~

Gratuluji! Právě jste zrychlili vytváření instancí presenterů **cca 4-násobně**!
V naší aplikaci je ten průměr někde kolem 3x zrychlení, ale může to klidně skočit i víc než 5x, v závislosti na složitosti presenteru.



## Dočasný fix pro Nette 2.2.3 (a možná i 2.2.*?)


Bohužel někdy v průběhu existence Nette 2.2 se tam [vyskytla chybka](https://github.com/nette/application/blob/0c5280fa75bd237afd179b50961cee2de8e8e32a/src/Application/PresenterFactory.php#L62), že DI Container a PresenterFactory nebyly sehrané úplně na 100% a tedy i přesto, že byla instance vytvořena přes DI Container, se prováděla analýza závislostí a předávaly se znovu. Což je opět zbytečně propálenej výkon. Tohle je opravené ve vývojové verzi, ale jak jsme se již opakovaně poučili, používat vývojové verze Nette je forma extrémního sportu, takže se tomu budeme snažit co nejvíce vyhnout.

Do `composer.json` si hoďte tyto specifické verze, používám je právě v moment psaní článku (tedy během pár měsíců budou outdated, bacha na to!)

~~~ js
"require": {
    "nette/nette": "dev-master#e23de7ab as 2.2.99",
    "nette/di": "dev-master#97994498 as 2.3.99",
    "nette/neon": "~2.3@dev",
    "nette/utils": "~2.3@dev"
}
~~~

Samozřejmě můžete i novější, ale u těch vám nemůžu garantovat, že to bude fungovat :)

V `nette/di` přibyly dvě rozšíření, `InjectExtension` a `DecoratorExtension`,
mělo by být bezpečné je takto přidat v `app/bootstrap.php`.

~~~ php
$configurator = new Nette\Configurator();
$configurator->defaultExtensions['decorator'] = Nette\DI\Extensions\DecoratorExtension::class;
$configurator->defaultExtensions['inject'] = Nette\DI\Extensions\InjectExtension::class;

// ...
// zbytek app/bootstrap.php
~~~

V tenhle moment už máme v aplikaci nové DI, které využívá tagy pro označení tříd, na kterých proběhl resolve "inject závislostí" (tedy public properties s `@inject` a `inject*()` metody).
Díky tomu je možné i v runtime ověřit, jestli je potřeba třídě resolvovat inject závislosti, nebo už tato operace proběhla. Což jsme přesně potřebovali vědět,
abychom mohli podmínit resolve injectů pro presentery.

Vytvoříme si tedy vlastní `PresenterFactory` a [zkopírujeme do něj metodu `createPresenter`](https://github.com/nette/application/blob/226c1f1deb00cfeb1c4e60bdb5eaa962775afd8e/src/Application/PresenterFactory.php#L47-L64) z dev verze `nette/application`, kde už je problém vyřešený.


~~~ php
class PresenterFactory extends Nette\Application\PresenterFactory
{
    /** @var Nette\DI\Container */
    private $container;

    public function __construct($baseDir, Nette\DI\Container $container)
    {
        parent::__construct($baseDir, $container);
        $this->container = $container;
    }

    public function createPresenter($name)
    {
        $class = $this->getPresenterClass($name);
        if (count($services = $this->container->findByType($class)) === 1) {
            $presenter = $this->container->createService($services[0]);
            $tags = $this->container->findByTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);
            if (empty($tags[$services[0]])) {
                $this->container->callInjects($presenter);
            }

        } else {
            $presenter = $this->container->createInstance($class);
            $this->container->callInjects($presenter);
        }

        if ($presenter instanceof UI\Presenter && $presenter->invalidLinkMode === NULL) {
            $presenter->invalidLinkMode = $this->container->parameters['debugMode']
                ? UI\Presenter::INVALID_LINK_WARNING
                : UI\Presenter::INVALID_LINK_SILENT;
        }

        return $presenter;
    }
}
~~~

A nezapomenout zaregistrovat :)

~~~ neon
services:
    presenterFactory: My\PresenterFactory
~~~


V momentě kdy se dostane do nějaké stable verze tenhle kód (tedy opravené nette/application a nette/di) je možné vlastní `PresenterFactory` a přidávání nových DI extensions (inject a decorator) vyhodit z aplikace, protože to bude už přímo v Nette.



## UPDATE: Je libo extension?

Zásluhou [@MiraPaulik](https://twitter.com/MiraPaulik), který mě vyhecoval, jsem tomu věnoval pár hodin navíc a udělal z toho extension,
který je k dostání [na githubu jako Kdyby/PresentersLocator](https://github.com/Kdyby/PresentersLocator).
Zatím existuje pouze dev verze, takže pro instalaci dát do composeru

~~~ js
"require": {
    "kdyby/presenters-locator": "@dev"
~~~

A povolit třeba takto

~~~ neon
extensions:
    presenters: Kdyby\PresentersLocator\DI\PresentersLocatorExtension
~~~

Mělo by to být dost chytré na to, aby to nezkoušelo ani autoloadovat classy, které nevypadají jako presentery.
A kdyby to vyloučilo něco co by nemělo, tak to můžete přetížit [pomocí whitelistu](https://github.com/Kdyby/PresentersLocator/blob/b610e3202cf9db47f3a238996191c8095428c2e3/src/Kdyby/PresentersLocator/DI/PresentersLocatorExtensions.php#L30-L32).

Umí to načítat pomocí RobotLoaderu z `%appDir%` a taky classmapu Composeru.
Takže pokud chcete, můžete třeba vypnout robotloader pomocí

~~~ neon
presentersLocator:
    scanAppDir: off
~~~

přidat si `%appDir%` do composeru

~~~ js
"autoload": {
    "classmap": ["app/"]
}
~~~

a pak říct composeru, ať udělá práci co do teď dělal RobotLoader

~~~ shell
$ composer dump-autoload --optimize
~~~

Najde všechny třídy a uloží je do "statické" cache, kterou když budete chtít přegenerovat, musíte opět pustit `composer dump-autoload`.
Na druhou stranu, nebude vás brzdit RobotLoader :)

----

Dočasně, než nám [opraví packagist](https://github.com/composer/packagist/issues/458), by mělo fungovat přidat si repozitář do `composer.json`

~~~ js
"repositories": [
    { "type": "vcs", "url": "https://github.com/Kdyby/PresentersLocator.git" },
~~~
