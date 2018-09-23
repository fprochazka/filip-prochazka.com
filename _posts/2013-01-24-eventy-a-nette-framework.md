---
layout: blogpost
title: "Eventy a Nette Framework"
permalink: blog/eventy-a-nette-framework
date: 2013-01-24 01:00
tag: ["Nette Framework", "Doctrine", "Kdyby", "PHP"]
---

Vyčleňuji právě [svoji integraci Doctrine](https://github.com/kdyby/doctrine) do Nette Frameworku a jedna její část řeší údálosti.

Doctrine má na události jednoduchý systém - existuje třída `EventManager`, do které se registrují listenery a když se "něco stane", vyvoláme nad ní událost a ta se předá příslušným listenerům. Pro detaily si můžete odskočit do [podrobné dokumentace](http://docs.doctrine-project.org/en/latest/reference/events.html).

Nette Framework [má také události](http://doc.nette.org/cs/php-language-enhancements#toc-udalosti). Používáte je nejspíše každý den ve formulářích, když nastavujete `$form->onSuccess[] = $callback;`.

A mě napadlo: co kdybych to sjednotil?

(Pro plné pochopení článku je nutné znát [použití obou systémů](http://doc.nette.org/cs/php-language-enhancements#toc-udalosti), tak si to [skočte přečíst](http://docs.doctrine-project.org/en/latest/reference/events.html), já tu počkám)

<!--more-->
## Autowire událostí

Mějme jednoduchou třídu

~~~ php
class OrderProcess extends Nette\Object
{
    public $onSuccess = array();
    private $orders;

    public function __construct(Orders $orders)
    {
        $this->orders = $orders;
    }

    public function process($values)
    {
        if ($order = $this->orders->create($values)) {
            $this->onSuccess($this, $order);
        }
    }
}
~~~

Protože používám `Nette\Object`, mohu si navázat libovolné callbacky, na událost `$onSuccess`.

~~~ php
$process = new OrderProcess($orders);
$process->onSuccess[] = function ($process, $order) {
    echo "Utratil jsi ", $order->sum, " Kč";
};
~~~

Na druhé straně stojí Doctrine eventy, které jsou takové "globálnější". Zavolám z jednoho místa a vůbec nemám ponětí, ke komu se to dostane. Protože mezi tím, kdo událost vyvolá a tím, kdo naslouchá, stojí `EventManager`.

Další věc je, že zapisování listenerů pro Nette eventy v DIC je velice nepěkné

~~~ neon
services:
    orderProcess:
        class: OrderProcess()
        setup:
            - "$service->onSuccess[] = ?"([@listenerService, method])
~~~

Oba systémy se dají propojit, když navážu jeden listener, který by argumenty automaticky předal do `EventManager`.

~~~ php
$process->onSuccess[] = function () use ($eventManager) {
    $eventManager->dispatch('OrderProcess::onSuccess', new SuccessEventArgs(func_get_args()));
}
~~~

Před půl rokem mi David mergl [důležitý patch](https://github.com/nette/nette/pull/730), abych celé tohle mohl rapidně usnadnit. Vytvořil jsem speciální třídu `Event`, která automaticky události deleguje.

~~~ php
$process->onSuccess = new Event('OrderProcess::onSuccess');
$process->onSuccess->injectEventManager($eventManager);
~~~

Funkčnost zústala zachována, ale nyní se všechny volání automaticky delegují do `EventManager`u a může naslouchat kdokoliv.

A protože jsem jako správný programátor velice líný, napsal jsem si automatiku, která mi během kompilace projde všechny služby v Nettím DIC a pokud služba obsahuje nějaké události, konvertuje je na instance třídy `Event`.

Díky tomu mohu napsat například listener, který bude naslouchat na `Application::onStartup`

~~~ php
class FooListener extends Nette\Object implements Kdyby\Events\Subscriber
{
    public function getSubscribedEvents()
    {
        return array('Nette\Application\Application::onStartup');
    }

    public function onStartup(Application $app)
    {
        // tohle se zavolá při každém startu aplikace
    }
}
~~~

Listener zaregistruji a dám mu tag. Díky tomu se automaticky připojí do `EventManager`u.

~~~ neon
services:
    foo:
        class: FooListener
        tag: [kdyby.subscriber]
~~~


## Optimalizace výkonu

Tohle všechno má jednu zřejmou nevýhodu - pokud chci události použít, musím vždy dopředu připravit callback, nebo listener, který se nemusí vůbec zavolat. Například u formulářů je nám to celkem jedno, tam předáme jako callback nějakou metodu aktuálního objektu. Kdyby to byla ale událost, která se má propagovat do celého systému, už by to byl problém. Každá událost má totiž nějaké závislosti a úplně všechny by se nám inicializovaly v momentě vytvoření `EventManager`u, nebo nějaké služby, která jej vyžaduje, či spouští event.

Implementoval jsem proto také automatickou optimalizaci, která všechny listenery zanalyzuje a vytvoří mapu pro `EventManager`, podle které může listenery aktivovat lazy. Nic mě to tedy nestojí a můžu jich mít kolik chci.


## Na co je to ale dobré?


Představte si, že chcete posílat uživateli emaily s potvrzením, že objednávka proběhla. Standardní postup by byl zhruba takový, že bych si do `OrderProcess` předal něco co mi bude maily posílat `Nette\Mail\IMailer`, něco co mi pro ten email vytvoří šablonu a možná ještě další závislosti. I kdybych tohle celé zabalil do služby, stejně tam tuto službu musím předat. Objekt se stává závislým na nečem, co mi bude posílat emaily a nejspíše to bude muset i hlídat a ošetřovat chyby. Časem možná budu chtít přidat další funkcionalitu a objekt bude kynout a kynout.

Co kdybych ale posílání emailů vyřešil pomocí události?

~~~ php
class OrderMailerListener extends Nette\Object implements Kdyby\Events\Subscriber
{
    private $mailer;

    public function __construct(IMailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function onSuccess(OrderProcess $process, Order $order)
    {
        $this->mailer->send(new Message());
    }

    public function getSubscribedEvents()
    {
        return array('OrderProcess::onSuccess');
    }
}
~~~

Na původní třídu jsem nemusel vůbec sahat a najednou se mi při každé objednávce posílají emaily! No není to krása?

Tak a teď jsem se rozhodl, že budu posílat i SMSky, místo abych předával další službu, která mi bude tohle řešit, napíšu listener, a na původní objekt zase vůbec nesahám!


## Shrnutí

Co jsem tedy získal?

- Všechny události zapsané stylem pro Nette se mi automaticky převádí na "globální"
- Všechny listenery jsou lazy
- "Nový" způsob rozšiřování funkcionality objektů
- Nepoužíváte Doctrine? Nevadí, [Kdyby/Events](https://github.com/kdyby/events) je psané tak, že je s Doctrine plně kompatibilní, ale vůbec ji nevyžaduje.

Já se teď budu muset snažit, abych to nepoužíval i tam, kde se to moc nehodí, protože se mi tento koncept velice líbí.

Pokud si chcete [Kdyby/Events](https://github.com/kdyby/events) vyzkoušet, nainstalujte si pomocí [Composeru](http://getcomposer.org/) balíček [kdyby/events](https://packagist.org/packages/kdyby/events) a zaregistrujte extension `Kdyby\Events\DI\EventsExtension`.

Co vy, zaujalo vás to? Zkusíte to? Vidíte tam nějaký problém, nebo vás napadá jak to ještě vylepšit? Budu vděčný za každou reakci!
