---
layout: blogpost
title: "Píšeme rozšíření (compileru) pro Nette Framework"
permalink: blog/piseme-rozsireni-compileru-pro-nette-framework
date: 2012-11-06 10:50
tag: ["Nette Framework"]
---

Napsali jste užitečný kus kódu, o který se chcete podělit s Nette komunitou? Výborně! Ukážeme si, jak usnadnit použití takového kódu ostatním.

Vaše rozsíření nejspíše bude obsahovat nějaké užitečné třídy a tyto třídy bude chtít programátor používat v presenteru, nebo jimi třeba nahradit výchozí služby. Například já jsem psal rozsíření pro Redis. Toto rozšíření obsahuje třídu Redis klienta `RedisClient`, úložiště pro cache `RedisStorage`, žurnál `RedisJournal` a úložiště pro session `RedisSessionHandler`.

Třídy `RedisStorage`, `RedisJournal` a `RedisSessionHandler` vyžadují ke své funkčnosti instanci `RedisClient`. Jejich registrace do DIC pomocí configu by vypadala takto:


~~~ neon
common:
    services:
        redisClient: RedisClient()
        nette.cacheJournal: RedisJournal(@redisClient)
        cacheStorage: RedisStorage(@redisClient, @nette.cacheJournal)

        session:
            setup:
                - setStorage(RedisSessionHandler(@redisClient))
~~~


Asi by ale nebylo ideální, kdyby si kvůli použití rozšíření musel programátor plnit config balastem, který tam být nemusí.

Řešit se to dá velice elegantně napsáním vlastního [rozsíření compileru](http://doc.nette.org/cs/di-extensions). Takové rožšíření vlastně bude jen registrovat služby stejně jako neon config, ale bude to dělat pomocí [metod třídy ContainerBuilder](http://api.kdyby.org/class-Nette.DI.ContainerBuilder.html). Je to sice výrazně ukecanější než čistý neon config, ale získáme tím ohromnou flexibilitu.

Úplně stupidní přepis do `CompilerExtension` by vypadal takto


~~~ php
class RedisExtension extends Nette\DI\CompilerExtension
{
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        // metoda prefix dá před název služby název rozsíření
        // v tomto případě tak vznikne služba s názvem `redis.client`
        $builder->addDefinition($this->prefix('client'))
            ->setClass('RedisClient');

        $builder->removeDefinition('nette.cacheJournal');
        $builder->addDefinition('nette.cacheJournal')
            ->setClass('RedisJournal');

        $builder->removeDefinition('cacheStorage');
        $builder->addDefinition('cacheStorage')
            ->setClass('RedisStorage');

        $builder->getDefinition('session')
            ->addSetup('setStorage', array(
                new Nette\DI\Statement('RedisSessionHandler')
            ));
    }
}
~~~


Takové rozšíření se pak zaregistruje v `app/bootstrap.php`


~~~ php
$configurator->onCompile[] = function (Configurator $config, Compiler $compiler) {
    $compiler->addExtension('redis', new RedisExtension());
};
~~~


Nyní se rozšíření dá rozumně používat, ale šlo by to udělat lépe. Například budeme chtít změnit port, nebo adresu, kde Redis běží.

Každé registrované rozšíření, získá vlastní sekci v configu. Všimněte si jména, jaké jsem použil v příkladu nahoře, v metodě [addExtension()](http://api.kdyby.org/class-Nette.DI.Compiler.html#_addExtension).

~~~ neon
production:
    redis:
        host: 127.0.0.1
        port: 6379
        timeout: 10
        database: 0
~~~

Všechno, co pod stejnou sekcí napíši v configu, získám v rozšíření pomocí metody [getConfig()](http://api.kdyby.org/class-Nette.DI.CompilerExtension.html#_getConfig). Nejčastější chyba je, zanořovat sekci `redis` do `services`, nebo do `parameters`, na to si dejte pozor!

~~~ php
class RedisExtension extends Nette\DI\CompilerExtension
{
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();
        $config = $this->getConfig();

        $builder->addDefinition($this->prefix('client'))
            ->setClass('Kdyby\Redis\RedisClient', array(
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $config['database'],
                'timeout' => $config['timeout']
            ));
~~~

Dále bych taky chtěl mít nějaké rozumné výchozí hodnoty, s tím nám také pomůže metoda `getConfig()`

~~~ php
$config = $this->getConfig(array(
    'host' => 'localhost',
    'port' => 6379,
    'timeout' => 10,
    'database' => 0
));
~~~

Nyní, když některé, nebo klidně všechny parametry v sekci `redis` v configu vynechám, tak se použijí výchozí hodnoty.

Další cukrlátko by mohlo být vytvoření funkce `register()`, která by nám ušetřila psaní v `app/bootstrap.php`.

Místo

~~~ php
$configurator->onCompile[] = function (Configurator $config, Compiler $compiler) {
    $compiler->addExtension('redis', new RedisExtension());
};
~~~

[vytvoříme statickou funkci](https://github.com/Kdyby/Redis/blob/1df4d84a599e883255e76e53e6e1468ade127e25/src/Kdyby/Redis/DI/RedisExtension.php#L173-L181), která nám zkrátí zápis na prosté

~~~ php
RedisExtension::register($configurator);
~~~

Hotové a funkční rozšíření compileru pro Redis, si [můžete prohlédnout zde](https://github.com/Kdyby/Redis/blob/1df4d84a599e883255e76e53e6e1468ade127e25/src/Kdyby/Redis/DI/RedisExtension.php).

No a kdybychom chtěli například `RedisClient` použít v presenteru, tak použijeme [oblíbené `inject*()` metody](http://pla.nette.org/cs/inject-autowire).

~~~ php
class MyPresenter extends BasePresenter
{
    /** @var RedisClient */
    private $redisClient;

    public function injectRedis(RedisClient $client)
    {
        $this->redisClient = $client;
    }

    public function actionDefault()
    {
        $this->redisClient->get(...);
    }
}
~~~


**Update 2014-04-29**: Pozor, nově v Nette jdou registrovat rozšíření i přímo v configu, což je efektivnější, protože se pak nemusejí vůbec načítat na každý request, ale pouze když se aplikace kompiluje.
Pomocná funkce `register` pak není vůbec potřeba.

~~~ neon
extensions:
    redis: Kdyby\Redis\DI\RedisExtension
~~~
