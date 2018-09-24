---
layout: blogpost
title: "Kdyby/RabbitMq aneb asynchronní Kdyby/Events"
permalink: blog/kdyby-rabbitmq-aneb-asynchronni-kdyby-events
date: 2014-06-26 21:00
tag: ["Nette Framework", "PHP", "Kdyby", "RabbitMq"]
---

Pokud právě čtete tento článek, tak jste jistě četli i [Eventy a Nette Framework](http://filip-prochazka.com/blog/eventy-a-nette-framework) a pokud ne, tak doporučuji si na něj odskočit, já tu počkám.

Jak teď už všichni víme, Eventy jsou strašně silný nástroj, jenže i s Eventy se můžeme dostat do situace, že toho už sice nedělají příliš naše třídy, ale tentokrát celá aplikace.
Takové poslání emailu, komunikace s platební bránou, poslání smsky, komunikace s externím pokladním API a kdo ví co ještě nějaký čas zabere.

Nebylo by fajn, kdyby se některé naše eventy zpracovaly asynchronně, na pozadí, abychom nezdržovali uživatele? Tak přesně od toho je [RabbitMQ](https://www.rabbitmq.com/) a tedy i [Kdyby/RabbitMq](https://github.com/kdyby/rabbitmq).

Nebudu vás ale zatěžovat teorií, tu sepsal už [Jakub Kohout](http://www.jakubkohout.cz/2014/06/rabbitmq-easy-start.html) i s přehledem několika šikovných nástrojů, které se vám budou hodit. Tak až si to přečtete, pojďme rovnou skočit rovnýma nohama do praxe.

<!--more-->
## První producer

Potom co si nainstalujete RabbitMQ, jeho [management plugin](https://www.rabbitmq.com/management.html#getting-started) a [Kdyby/RabbitMq](https://github.com/Kdyby/RabbitMq/blob/master/docs/en/index.md#installation), tak budete muset nakonfigurovat producery a consumery.

Producer je v tomto případě nějaký nakonfigurovaný objekt, který už jen přijímá zprávy a posílá je do nějakého exchange.

~~~ php
$producer->publish($message);
~~~

Pro jednodušší použití, protože producerů bude určitě více a špatně by se autowirovaly (ovšem stále si je můžete předávat přes pojmenované služby), existuje třída `Kdyby\RabbitMq\Connection`. Pak třeba v presenteru můžete získat producer takto snadno.

~~~ php
/** @var \Kdyby\RabbitMq\Connection @inject */
public $bunny;

public function actionDefault()
{
    $producer = $this->bunny->getProducer('facebookNotifications');
}
~~~

V třídách s bussines logikou pak samozřejmě použijeme constructor injection pro `Connection`, případně si můžeme v configu rovnou předat konkrétní producer

~~~ neon
services:
    - App\FacebookNotifications(@rabbitmq.producer.facebookNotifications)
~~~

Jenže odkud se tyto producery vezmou? To je jednoduché, nakonfigurujeme si je. Hned na začátek si ale vytvoříme nový configurační soubor, protože nastavení rabbita se může pěkně protáhnout.

~~~ neon
# app/config/config.neon

includes:
    - rabbitmq.neon
~~~

~~~ neon
# app/config/rabbitmq.neon

rabbitmq:
    connection:
        user: guest
        password: guest

    producers:
        facebookNotifications:
            exchange: {name: 'facebook-notifications', type: direct}
            queue: {name: 'facebook-notifications'}
            contentType: application/json
~~~

Tak a máme nakonfigurované připojení k RabbitMQ serveru a první producer pro facebook notifikace, co je to exchange a queue už jistě víte z Jakubova článku.

Zmínit si ale zaslouží volitelná konfigurace `contentType`, jejíž výchozí hodnota je `text/plain`.
Nenechte se zmást, nemění to chování objektů, jsou to jenom metadata, aby si producer a consumer rozuměli.

Nyní si předáme `Connection` či rovnou producer a pošleme mu nějakou zprávu

~~~ php
$producer->publish(json_encode(['greet' => "Hello %name", 'name' => "Filip"]));
~~~

Protože jsme deklarovali, že budeme komunikovat v jsonu, musíme to sami dodržovat, protože mime typů jsou mraky, rošíření nemá šanci hlídat, že tam nepošlete hloupost. Prostě vezme co mu dáte a pošle to dál.

A pokud máte správně rozběhaný management plugin, uvidíte peak publikací nových událostí (ta žlutá dole)

> ![rabbitmq-publish](/content/rabbitmq-publish.png)
>
> <small>management plugin a publikace nových událostí</small>

Teď se vrhneme na jejich zpracování.


## První consumer

Consumery ke své funkci potřebují konzoli a to konkrétně Symfony Consoli, úplně nejkonkrétněji [Kdyby/Console](https://github.com/Kdyby/Console) :)

Jsou to samostatné procesy, které jsou trochu jako crony, ale mají maličko jinou povahu. Jsou to procesy, které nechcete pouštět každou minutu, ale chcete, aby takový proces běžel bez přestání a kdyby třeba kvůli nečemu umřel, tak se musí zapnout okamžite a nikoliv až za půl hodiny. Jsou to prostě nesmrtelní [daemoni](https://wiki.archlinux.org/index.php/Daemon).

Pro začátek si nakonfigurujme consumer do páru k našemu předchozímu produceru `facebookNotifications`.


~~~ neon
# app/config/rabbitmq.neon

rabbitmq:
    ...

    consumers:
        facebookNotifications:
            exchange: {name: 'facebook-notifications', type: direct}
            queue: {name: 'facebook-notifications'}
            callback: @App\Facebook\NotificationSender::process
~~~

Exchange a queue by ideálně měly být stejné, pokud tedy nepraktikujete pokročilé routování. Ovšem přibyla nám tu nová volba a to `callback`.
Jedná se ideálně o metodu nějaké služby, tedy v tom případě máme službu `App\Facebook\NotificationSender` s metodou `process`.

Pokud jsme vše správně nastavili, měl by jít pustit consumer pomocí symfony console commandu, který začne zprávy předávat našemu callbacku.

~~~ shell
$ php www/index.php rabbitmq:consumer facebookNotifications
~~~

Tímto jsme pustili proces a pokud nevyhodil žádnou chybu tak pravděpodobně běží, ověřit si to můžete tak, že ho spustíte s příkazem `--debug`.
Debug mód rozhodně nechcete pouštět na produkci, zvrací ohromné množství informací a zdržuje consumera.

Jelikož jsme před chvíli do fronty zařadili zprávu, tak v okamžiku spuštění consumera měla být předána našemu callbacku.
Ovšem nedostaneme string který obsahuje JSON, dostaneme objekt `PhpAmqpLib\Message\AMQPMessage`.

Pokud si věříme, že zpráva skutečně obsahuje JSON, tak můžeme rovnou dekódovat. Pokud ne, tak můžeme nejprve ověřovat `contentType` zprávy a automaticky třeba zvolit nativní unserialize nebo jinou formu deserializace.

Pro začátek data jenom vypíšeme.

~~~ php
class NotificationSender
{

    public function process(AMQPMessage $message)
    {
        $data = json_decode($message->body);
        var_dump($data);
    }

}
~~~

Nyní bychom v konzoli měli vidět dumpnutou message

> ![rabbitmq-consumer-debug](/content/rabbitmq-consumer-debug.png)
>
> <small>Na obrázku můžete vidět co z consumera leze, pokud ho spustíte v debug módu. Barevně je dump zprávy, protože ho dumpuji pomocí tracy dumperu. Šedé jsou debugovací informace o protokolu a probíhající komunikaci.</small>

A v managementu se graf taky pohne, protože jsme provedli nějakou práci.

> ![rabbitmq-consume](/content/rabbitmq-consume.png)
>
> <small>zpracování zpráv</small>

Abych to trošku zpestřil, tak já jsem si zapl rovnou 4 consumery na stejnou frontu a mohou tedy zpracovávat 4 zprávy konkurenčně, grafy jsou pak veselejší.



## Asynchronní Eventy?

Vezmeme si třeba `OrderMailerListener` z [předchozího článku](http://filip-prochazka.com/blog/eventy-a-nette-framework) a místo maileru si předáme producer.

~~~ neon
rabbitmq:
    producers:
        orderEmails:
            exchange: {name: 'order-emails', type: direct}
            queue: {name: 'order-emails'}
            contentType: application/json
~~~

~~~ neon
services:
    - {class: OrderMailerListener(@rabbitmq.producer.orderEmails), tag: [kdyby.subscriber]}
~~~

~~~ php
use Kdyby\RabbitMq\Producer;

class OrderMailerListener extends Nette\Object implements Kdyby\Events\Subscriber
{
    private $producer;

    public function __construct(Producer $producer)
    {
        $this->producer = $producer;
    }

    public function getSubscribedEvents()
    {
        return array('OrderProcess::onSuccess');
    }

    public function onSuccess(OrderProcess $process, Order $order)
    {
        $this->producer->publish(json_encode([
            'order' => $order->id,
            'user' => $order->user->id
        ]));
    }
}
~~~

A teď už jenom napsat třídu co bude posílat emaily a nakonfigurovat consumer, který ji bude zprávy předávat. To máte za domácí úkol :)


## Nesmrtelný daemon?

Na to jak udržet proces při životě je spousta technik, my jsme se rozhodli použít [supervisord](http://supervisord.org/).
Spouští jednotlivé procesy co mu zadáte, jako své vlastní podporocesy, a má tedy nad nimy výbornou kontrolu.

Po instalaci si vytvoříme konfigurační soubor a do sekce s programy dáme

~~~ ini
[program:damejidlo_rabbitmq_fbNotificationConsume]
command=/usr/local/bin/php /home/hosiplan/develop/damejidlo.cz/www/index.php rabbitmq:consumer -w -m 500 facebookNotifications
directory=/home/hosiplan/develop/damejidlo.cz
user=hosiplan
autorestart=true
process_name=%(process_num)02d
numprocs=4
~~~

Jak se konfigurují programy [máte i v dokumentaci](http://supervisord.org/configuration.html#program-x-section-settings), ale projděme si, co jsem tady spáchal já.

- Nejprve jsem program pojmenoval. Překvapivě se mu nelíbí, když má moc dvojteček v názvu, takže musím používat podtržítka (dvojtečky by byly hezčí).
- `command` definuje co se má spustit a s jakými parametry
- `directory` je pracovní složka, ve které se proces spustí. U našeho consumera by to mělo být jedno, ale jistota je jistota.
- `user` je také důležitý, chceme aby proces běžel se stejnými právy jako webserver, jinak nejspíš ani nenaběhne, protože by neměl právo zapisovat do tempu
- `autorestart` je to naše nejdůležitější kouzlo. Vždy, když proces z jakékoliv důvodu umře, tak ho supervisord okamžite zase spustí
- `process_name` je jenom kosmetika
- `numprocs` znamená že má pustit daný proces 4x paralelně (ne, sériově to neumí)

A co vlastně pouštím za command?

~~~ shell
rabbitmq:consumer -w -m 500 facebookNotifications
~~~

Raději si to projdeme znovu do detailů

- `rabbitmq:consumer` je název našeho symfony console commandu který celou práci zastřešuje
- Výchozí chování commandu je, že začne poslouchat na signály procesu a dokáže vám tedy zabránit zaříznout ho v polovině zpracování nějaké message. U jakéhokoliv jiného procesu, který máte zrovna spuštěný v konzoli, by stačilo CTRL+C a zabilo by ho to. Ale tady ne, protože na tento event naslouchá a jestli má umřít se kontroluje vždy po dokončení zpracování message. Tedy když ho budete chtít zabít, tak buďto `sudo kill -9 PID`, nebo si počkáte až dokončí svou práci. Od toho je tu parametr `-w` (neboli `--without-signals`), který tohle chování vypne a php proces bude možné zaříznout prostým CTRL+C a mimo jiné nad ním bude mít větší kontrolu supervisord
- posledním parametrem je `-m 500` a ten říká, že jakmile worker zpracuje 500 messagí, tak se má ukončit. Tahle volba je extra šikovná v kombinaci s automatickými restarty a faktem, že PHP není určené k psaní nekonečně běžících scriptů.
- a na konci je argument s názvem consumera z configu `facebookNotifications`

Nastaveno, spuštěno a pokud si zapnete i [webové rozhranní k supervisord](http://supervisord.org/configuration.html#inet-http-server-section-settings), tak můžete přes web sledovat stav svých workerů

![rabbitmq-supervisord](/content/rabbitmq-supervisord.png)


## Shrnutí

Ukázali jsme si jak nastavit nějakou základní frontu zpráv, jak do ní zapisovat a jak ji zpracovávat.
Taky už umíme vytvořit "nesmrtelné" procesy z PHP scriptů a tedy máme skvělý základ pro asynchronní zpracování jakékoliv práce v naší aplikaci.

Dovolím si ještě upozornit, že [Kdyby/RabbitMq má celkem rozsáhlou dokumentaci v angličtině](https://github.com/Kdyby/RabbitMq/blob/master/docs/en/index.md#usage) (byla převzata z [původního Symfony Bundle](https://github.com/videlalvaro/RabbitMqBundle), jehož je Kdyby/RabbitMq forkem, a upravena pro Nette). A taky mějte na paměti, že RabbitMq teprve nasazujeme a může ještě pár týdnů trvat, než bude Kdyby/RabbitMq vyladěný úplně do zlatova.
Ovšem to vám vůbec nebrání jít si ho nainstalovat a pohrát si s ním už teď :)

Používáte taky nějaký message broker? Máte vlastní implementaci do aplikace? Pokud ano, podělte se prosím v komentářích o myšlenky a problémy, které tam máte vyřešené lépe. Díky!
