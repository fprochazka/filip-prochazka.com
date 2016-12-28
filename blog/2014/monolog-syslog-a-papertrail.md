Date: 2014-09-08 21:45
Tags: Monolog, Kdyby, Nette Framework, PHP

# Kdyby/Monolog + syslog + papertrail

U každé aplikace, která vydělává nějaké peníze, dříve nebo později zjistíte, že potřebujete logovat někam co se děje a co kdo udělal.
Někteří k tomu používají databázi a `_audit` tabulky, ale ty se nehodí na všechno.
Někdy je prostě jednodušší to nasázet do souborů, a pak už se k nim nikdy nevrátit, protože to nikoho nebaví chodit kontrolovat :)
Ale co si budem, v té databázi by to taky nikdo nekontroloval :)

Kombinaci nástrojů z titulku používáme v DameJidlo.cz i Rohlik.cz a budu ji rozhodně používat všude kde to půjde, pojďme si ukázat proč a jak :)


## Monolog

Do nedávna jsme používali "hloupé"

```php
Nette\Diagnostics\Debugger::log($nejakaZprava, 'emaily');
```

což je takové chytřejí `file_put_contents()`. Pěkně to vytvoří soubor `log/emaily.log` a do něj uloží obsah proměnné `$nejakaZprava`.
Používali jsme to úplně na všechno co vás napadne, ale pak jsem si řekl, že by to určitě šlo i čistěji, bez statiky.

Symfony svět používá masivně nástroj [Monolog](https://github.com/Seldaek/monolog), takže jsem ho vyzkoušel a okamžitě vznikl balík [kdyby/monolog](https://packagist.org/packages/kdyby/monolog)

Používá se velice jednoduše - tam kde chcete něco logovat tak si pošlete třídu `Monolog\Logger` a voláte metody, které indikují závažnost.

```php
use Monolog\Logger;

class EmailQueue extends Nette\Object
{
	/** @var Logger */
	private $logger;

	public function __construct(Logger $logger)
	{
		$this->logger = $logger;
	}

	public function operation()
	{
		try {
			// some logic
			$this->logger->addDebug('povedlose');

		} catch (SendingFailed $e) {
			$this->logger->addWarning('fail: ' . $e->getMessage());
		}
	}

}
```

Je trošku stupidní, že kvůli [kompatibilitě s PSR](http://www.php-fig.org/psr/psr-3/) tam mají všechny metody 2x,
tedy `addWarning` i `warning` (a dokonce i `warn`), ale zase tak moc mě to netrápí, prostě si vyberte jednu konvenci a ty používejte :)



## (r)syslog

Monolog má nějakých [plus mínus 40 handlerů](https://api.kdyby.org/namespace-Monolog.Handler.html),
což jsou různé služby, kam může zprávy přeposílat. Ať už na lokálním systému nebo na nějaký vzdálený.

Jednou z nich je právě linuxový syslog, do kterého můžete velice efektivně naládovat zprávy a on je pak piánko rozroutuje podle vašich pravidel.

Skvělé na tom je, že dokáže routovat i do vzdálených uložišť. O tom ale až za chvilku.


## Kdyby/Monolog => syslog

Nainstalujeme [kdyby/monolog pomocí composeru](https://github.com/kdyby/monolog#installation) a aplikaci si pojmenujeme

```neon
extensions:
	monolog: Kdyby\Monolog\DI\MonologExtension

monolog:
	name: damejidlo
```

Přidáme handler, který bude všechno posílat do syslogu

```neon
monolog:
	handlers:
		- Monolog\Handler\SyslogHandler('damejidlo', 'local4')
```

Proč `local4`? Nemám ponětí, byl to první volný kanál na VPS kde nám běží projekty, tak ho prostě používám všude :)

Uživatelé NewRelicu, stejně jednoduše přidají handler na NewRelic

```neon
monolog:
	handlers:
		- Kdyby\Monolog\Handler\NewRelicHandler(Monolog\Logger::NOTICE)

		# v Nette 2.1 možná budete muset použít trik:
		# Kdyby\Monolog\Handler\NewRelicHandler(::constant('Monolog\Logger::NOTICE'))
```

A pokud používáte nějaký jiný tool na integraci NewRelicu do Nette, tak v něm doporučuji zpracování chyb vypnout, ať se to nemlátí.

Výborné je, že můžete mít těch handlerů hromadu a každému by měl jít nastavit minimální level chyby, který je zajímá.
Všimněte si například, že do `SyslogHandler` level nenastavuji a nechávám vychozí `DEBUG`, tedy tam bude padat úplně všechno,
ale mezi `DEBUG` a `NOTICE` je ještě `INFO`, [viz api](https://api.kdyby.org/class-Monolog.Logger.html#constants).
To znamená, že `NewRelicHandler` nebude přeposílat zprávy které jsou `DEBUG` nebo `INFO`, ale všechny závažnější už ano.

Jak už je zvykem, [kdyby/monolog](https://packagist.org/packages/kdyby/monolog)
za vás řeší kompatibilitu s Nette napříč verzemi od 2.1 až do 2.3-dev a přidává cukrlátka.

Například vlastní `NewRelicHandler` v Kdyby. Monolog přímo obsahuje vlastní `NewRelicHandler`,
ale ten háže výjimky, když není v PHP extension na NewRelic a tím pádem pak musíte řešit podmiňování registrace služeb a to je prostě otrava... Takže jsem ho podědil a moje varianta se chová tak, že když není NewRelic nainstalovanej, tak se jednoduše ignoruje.
Ale samozřejmě pokud rádi trpíte, tak není problém zaregistrovat původní handler :)

Dalším takovým cukrlátkem je, že když nenastavíte žádné handlery, automaticky se zapne fallback,
který zapisuje logy do složky `log/`, jak jsme všichni zvyklí.
My máme s logy dlouhodobě problémy (je jich moc), takže je vůbec na lokální systém neukládáme a rovnou je tlačíme do syslogu.
Proto se úplně stejně chová i [kdyby/monolog](https://packagist.org/packages/kdyby/monolog) a v momentě kdy přidáte vlastní handlery,
tak se fallback co zapisuje do `log/` sám nezapne. Pokud tedy chcete stále zapisovat do `log/`, jednoduše nastavte

```neon
monolog:
	registerFallback: yes
```

Což se hodí zejména na locahostu, jinak toho moc neodladíte :)


A tím se konečně dostáváme k hlavnímu důvodu, proč jsem tohle všechno podstupoval - papertrail.


## Papertrail

Určitě to všichni znáte, máte jeden server a tak na logy občas kouknete.
Jenže pak zjistíte, že v tom máte buďto nehoráznej bordel,
nebo začaly přibývat servery a kdo by ty logy furt kontroloval a nedej bože když v nich potřebujete něco najít.

Tenhle problém neskutečně elegantně řeší služba [papertrailapp](https://papertrailapp.com/?thank=4f93db) (affil link, díky),
která umí logy za pár šupů agregovat a strašně elegantně v nich hledat.

Vytvořit účet a začít na papertrail posílat logy je úplně stupidně jednoduché.
V mém případě to tedy bylo nakopírovat na konec souboru `/etc/rsyslog.conf` následující řádek a restartovat službu rsyslog.

```
*.*          @logs2.papertrailapp.com:12345
```

V ten moment mi začalo do papertrailu padat všechno, co mi ve VPS teče přes rsyslog.
Například jsem poprvé v životě zjistil, že k `sshd` existuje log a konstantně se mi snaží VPS hacknout číňani :)

![monolog-papertrail-cn-hacking-vps](/content/monolog-papertrail-cn-hacking-vps.png)


## Výsledek

Pokud jste všechno správě nastavili, zkuste například vlézt na stránku, která na vašem webu neexistuje a koukejte co spadne do logu :)

![monolog-papertrail-archivist-access-info](/content/monolog-papertrail-archivist-access-info.png)

A nebo pokud bych byl tak hloupej, že bych si na produkci zakomentoval nějakou property, tak uvidím tohle

![monolog-papertrail-archivist-exception](/content/monolog-papertrail-archivist-exception.png)

A protože jsem si nastavil i reportování do NewRelicu, tak po chvilce se objeví i tam :)

![monolog-newrelic-errors](/content/monolog-newrelic-errors.png)

Samozřejmě budou vidět i všechny zprávy, které tam pošlete pomocí `Monolog\Logger::addInfo()` a dalších metod.

My si například posíláme do papertrailu úplně všechny odchozí smsky a u emailů komu jdou a předmět.
Na serverech tyhle věci zabíraly nechutné množství místa a takhle se nám samy po měsíci archivují na S3, protože to papertrail taky umí automaticky, pokud mu to nastavíte :)

Cokoliv vás zajímá, tak si můžete kdykoliv v logu vyhledat a jednotlivé searche si můžete "záložkovat". Když v nich pak něco přibude, tak je papertrail umí i někam posílat. Například na PagerDuty, které vám pošle SMSku, nebo notifikaci na smartphone :)

A pokud neplánujete používat papertrail, tak vyzkoušejte aspoň [Kdyby/Monolog](https://github.com/Kdyby/Monolog) :)
