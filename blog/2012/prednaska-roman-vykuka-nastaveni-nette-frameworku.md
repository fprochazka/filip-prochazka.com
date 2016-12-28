Date: 2012-10-07
Tags: Nette Framework, Přednáška

# Přednáška: Roman Vykuka - Nastavení Nette Frameworku

V [Developer Hubu](http://www.developerhub.cz/) nedávno [přednášel Roman Vykuka](https://plus.google.com/events/ccpdssb1cl85tpcgrbn9rmpfk0o) na téma konfigurace Nette Frameworku. Na záznam se můžete podívat na [YouTube](http://youtu.be/thr-pLDuOOU)

> <iframe width="560" height="315" src="http://www.youtube.com/embed/thr-pLDuOOU" frameborder="0" allowfullscreen></iframe>

"Čistý vývoj"-nazi ve mně ovšem **potřebuje** opravit pár drobných nepřesností, kterých se Roman, ve své přednášce, dopustil. Slovíčkařit nebudeme, nervozita dělá svoje.

Tohle prosím Romane neber jako útok, ale jako návrhy na vylepšení. Kdybych u té přednášky byl, tak bych ti to řekl osobně, takto to nejde :)


## Autowiring

Roman ukazoval [Autowiring](http://doc.nette.org/cs/configuring#toc-auto-wiring), na dost nešťastném příkladu. Do statické metody si předával celý DI Container.

``` php
class Authorizator extends Nette\Security\Permission
{
	// ...

	/**
	 * @return Nette\Security\IAuthorizator
	 */
	public static function create(Nette\DI\Container $container)
	{
		$cache = new Nette\Caching\Cache($container->cacheStorage);

		// pokud existuje authorizator v cachi, tak načíst
		if ($cache->load('authorizator') !== NULL) {
			return $cache->load('authorizator');
		}

		// jinak vytvořit novou a uložit do cache
		return $cache['authorizator'] = new static(
			$container->model->role->fetchAll(),
			$container->model->permission->fetchAll(),
			$container->model->resource->fetchAll()
		);
	}
}
```

Kód jsem maličko upravil, aby dával smysl. Neznám konkrétní implementaci, takže jsem to co nejvíce zjednodušil.

``` neon
services:
	authorizator:
		factory: Nisa\Security\Authorizator::create()
```

Předávat si celý DI Container je omluvitelné pouze ve dvou případěch

- pokud se rozhodnete, že v presenterech budete používat `$this->context`, což nezáří čistotou, ale je to velice praktické
- pokud hackujete DI Container a optimalizujete skládání služeb, nebo něco souvisejícího

"Správně" by to mělo vypadat takto nějak


``` php
class Authorizator extends Nette\Security\Permission
{
	// ...

	/**
	 * @return Nette\Security\IAuthorizator
	 */
	public static function create(
		Nette\Caching\IStorage $cacheStorage,
		Nisa\Model\Roles $roles,
		Nisa\Model\Permissions $permissions,
		Nisa\Model\Resource $resources)
	{
		// ...
	}
}
```

Ještě bych dodal, že statické továrničky jsou jinak naprosto v pořádku.


## Práce s kontejnerem v aplikaci

Kód z prvního slajdu bohužel fungovat nebude vůbec. Jsem pro zjednodušování v zájmu snadnějšího vysvětlení, ale tady jsme si to zjednodušili až moc.

```php
class MyPresenter extends BasePresenter
{
	public function __construct(Nette\Mail\IMailer $mailer)
	{
		$this->mailer = $mailer;
		$articles = $this->context->model->articles;
	}

	protected function createComponentArticleEditor()
	{
		return $this->context->createArticleEditor('test');
	}
}
```


Použivání konstruktoru presenteru pro předávání závislostí je samozřejmě naprosto v pořádku. Jak říkal Roman, `PresenterFactory` sama zavolá metody `injectPrimary` nad presenterem a předá do ní DI Container. Problém je v tom, že technicky se volá až po konstruktoru. Není tedy možné sahat na `$this->context` a vytahovat z něj služby, pokud do presenteru ještě nebyl předán.


## Komponenty v DIC

Roman také ukazuje, jak se dají vytvářet komponenty pomocí DIC. Stejné nadšení jsem [při uvedení feature měl také](http://forum.nette.org/cs/9418-komponenty-v-dic-pomoci-novych-tovarnicek). Jenomže používání továrniček v DIC přináší několik problémů, na které ale v jednoduché aplikaci většinou nenarazíte.

Asi nejhorší problém je, že takovéto komponenty se nedají moc pěkně skládat. Protože do presenteru se nedá injektnout továrnička, nedá se injektnout ani nikam jinam, ani do komponenty samotné. Jsme tedy odkázáni na používání `$this->context` v presenteru a `$this->presenter->context` v komponentách. V modelových třídách je to prakticky neřešitelné. Jediné co se dá udělat, tak předávat si celý DIC do modelových tříd. Ale z toho už jsme doufám vyrostli.

V současné době je jediným řešením [si napsat factory nebo builder třídu](http://forum.nette.org/cs/11883-vicenasobne-pouziti-formulare-dedicnost-nebo-tovarna), která bude vytvářet konkrétní typ komponenty. Nepovažuji to za problém, protože se to dá [celkem jednoduchým způsobem vylepšit](http://forum.nette.org/cs/11555-dependency-injection-factories-konecne-pouzitelne-tovarnicky). Roman tohle téma zmiňoval, ale nevysvětlil proč je to problém.


## Dynamické načítání konfigů

Používat `NeonAdapter` na načítání neon souborů je trochu zbytečné. Pokud v `modules.neon` nepoužíváš sekce, tak ti stačí základní `Nette\Utils\Neon::decode()`.

Také bych zvážil přesunutí logiky načítání konfiguračních souborů modulů, do `CompilerExtension`. Třeba do toho tvého základního pro CMS.


## Otázky na konec

Co se týče používání `$this->context` v presenteru, tak jak jsem psal výše, je to pohodlné a neodsuzuju to. Ale z hlediska čistoty bych raději používal [inject*() metody](http://pla.nette.org/cs/inject-autowire).


## Závěrem

Koukni na [Composer](http://doc.nette.org/cs/composer), pomůže ti se skládáním závislostí modulů ;)

Díky za přednášku, kde ti mám dát follow?
