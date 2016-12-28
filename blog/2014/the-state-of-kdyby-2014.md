Date: 2014-06-19 15:30
Tags: Nette Framework, Kdyby

# The state of Kdyby 2014

Nedávno byla vydána verze Nette 2.2, tak si pojďme udělat rekapitulaci jak na tom je Kdyby.

[Kdyby](https://www.kdyby.org/) dělám především pro sebe, proto vyvýjím předně věci, které sám používám a když něco nepoužívám, tak to nevyvíjím. Na druhou stranu bych nerad, aby takové projekty vyloženě umíraly.

*Pozor! Obsah je aktuální k 19.6.2014 a nebudu se vracet a tento článek doplňovat.*


## Aktivně vyvíjené balíčky

Tohle je seznam balíčků, které v tento moment používám a všechny standardní use-cases mi fungují na 100% a výhledově nehrozí, že bych se jim přestal věnovat.

- [annotations](https://packagist.org/packages/kdyby/annotations)
- [autowired](https://packagist.org/packages/kdyby/autowired)
- [console](https://packagist.org/packages/kdyby/console)
- [curl](https://packagist.org/packages/kdyby/curl)
- [curl-ca-bundle](https://packagist.org/packages/kdyby/curl-ca-bundle)
- [doctrine](https://packagist.org/packages/kdyby/doctrine)
- [doctrine-cache](https://packagist.org/packages/kdyby/doctrine-cache)
- [doctrine-money](https://packagist.org/packages/kdyby/doctrine-money)
- [events](https://packagist.org/packages/kdyby/events)
- [facebook](https://packagist.org/packages/kdyby/facebook)
- [github](https://packagist.org/packages/kdyby/github)
- [google](https://packagist.org/packages/kdyby/google)
- [money](https://packagist.org/packages/kdyby/money)
- [monolog](https://packagist.org/packages/kdyby/monolog)
- [rabbitmq](https://packagist.org/packages/kdyby/rabbitmq) je "the hot stuff", balík který v moment psaní tohoto článku zrovna dokončuji, stay tuned na článek :)
- [redis](https://packagist.org/packages/kdyby/redis)
- [translation](https://packagist.org/packages/kdyby/translation)

V následujícím týdnu až dvou otaguji master branch a ještě předtím dotáhnu zmenšení závislosti na Nette. U některých balíčků už jsem se do toho pustil, ale není to dotažené.

Proč jsem čekal tak dlouho? Jednak na to nebyla priorita a druhak jsem se už poučil a raději počkám, až se api v Nette ustálí, než abych projekt 4x přepisoval. 



## Experimenty (u ledu)

Pak tu máme skupinu rozšíření, které bych rád dotáhl, ale pořádně je nikde nepoužívám, takže nejsou priorita.

- [clock](https://packagist.org/packages/kdyby/clock) měl být pokus o vyřešení "datetime" problému v PHP, ale hlavně měl definovat datetime jako službu. Na co je to dobré? Tak například když celý systém počítá s tím, že aby věděl kdy je "teď" tak musí chtít službu, která mu to řekne a nebude si "teď" lovit ze vzduchu. Díky tomu půjdou jednotlivé třídy závislé na čase výborně testovat
- [doctrine-forms](https://packagist.org/packages/kdyby/doctrine-forms) je funkční tak z 65% toho co by měl umět. Pokud chcete urychlit vývoj, můžete se zapojit [třeba takto](https://github.com/Kdyby/DoctrineForms/issues/6).
- [validator](https://packagist.org/packages/kdyby/validator) úzce souvisí s doctrine-forms a má to být integrace symfonního validatoru do Nette, bohužel je stále ve fázi experimentu, protože jsem ještě neměl příležitost ho pořádně vyzkoušet

U balíků, které považuji za "exprimenty" je pro mě důležité si vyzkoušet použití na reálné aplikaci. Nechci vyvíjet rozšíření, jenom proto, abych vyvíjel rozšíření. Musí mít nějakou přidanou hodnotu, musí se dobře používat, musí vám zpříjemňovat práci nebo šetřit čas.

Taky je potřeba zmínit, že si rád dělám věci po svém. U balíků, které jsou v předchozí kategori, je už všechno důležité hotové a opravují a přidávají se už jen drobnosti. U těchle balíků ale ještě ani pro mě není pořádně jasný jakým směrem má vývoj jít a jak by se vlastně měly používat. Proto taky mám problém přijímat v nich pullrequesty, protože nedokážu posoudit, jestli kód co pošlete je přínosem (není to proto že by třeba byl pull špatně udělaný, ale opravdu to nedokážu objektivně posoudit).


A máme tu ještě jeden balík, který je stále ještě experiment, ale směr už je jasný.

- [aop](https://packagist.org/packages/kdyby/aop) přidává podporu AOP do Nettího DI Containeru a chybí už v podstatě jen drobnosti, z nichž většina je v issues. Pár lidí to zjevně používá, protože mi chodí pullrequest a mám z toho velkou radost.

Pro AOP bohužel na aktuálním projektu využití také nemám, takže jsem s vývojem na chvíli přestal, ale jak říkám, směr je jasný, stačí dopsat testy a pár features a jsme production-ready.



## Mnou neudržované projekty

V posledních několika letech jsem potřeboval i další věci na projekty, které už nevyvíjím. Jsou ta následující balíčky

- [bootstrap-form-renderer](https://packagist.org/packages/kdyby/bootstrap-form-renderer) pomáhal s renderováním formulářů stylem předepsaným CSS frameworkem Twitter Bootstrap 2 a taky přidával pár vlastních helperů na vykreslení třeba jen části formuláře.
- [forms-replicator](https://packagist.org/packages/kdyby/forms-replicator) normálně používám, protože mi funguje, ovšem je v něm pár nahlášených bugů, ale nikdo zatím neposlal pull s opravou a testem, takže ho lidé buď přestávají používat, nebo je chyba netrápí :)
- [filesystem](https://packagist.org/packages/kdyby/filesystem) je teď už úplně mrtvý projekt, protože Nette má vlastní Filesystem classu. Jediný smysl existence by mohlo být zapouzdření složky a souboru do objektů, ale na to už jsem viděl balík pro Nette u někoho jiného a možná svůj filesystem nechám umřít úplně. Používáte ho vůbec někdo? Nechci ho vyloženě mazat, abych někomu něco nerozbil.
- [html-validator-panel](https://packagist.org/packages/kdyby/html-validator-panel) byl vyloženě jenom experiment, ani nevím jestli ještě funguje a jestli ho někdo používá
- [nette-session-panel](https://packagist.org/packages/kdyby/nette-session-panel) tohle rozšíření jsem udělal asi necelý měsíc předtím, než David udělal to samé přímo do Nette. Ovšem stále se o něj stará [@enumag](https://twitter.com/enumag), protože to umí nějaké věci navíc.
- [qr-encode](https://packagist.org/packages/kdyby/qr-encode) je php wrapper nad konzolovou linuxovou utilitou, která renderuje QR kódy. Naposledy jsem ho použil před víc jak rokem.
- [svg-renderer](https://packagist.org/packages/kdyby/svg-renderer) je php wrapper nad inkscapem, který vezme SVG a vyrenderuje z něj png. Účel byl renderovat obrázky, které si nakreslí uživatel v svg kreslítku na webu.
- [selenium](https://packagist.org/packages/kdyby/selenium) byl experiment (fungující!) jak integrovat behat, selenium, pageobjekty a hromadu dalších udělátek do nette/testeru ... zkrátka jsme si to celý implementovali sami, protože jsme chtěli něco co se bude snadno psát a bude to dobře fungovat. Naše naivita zřejmě neměla hranic, když jsme si mysleli, že můžeme zjednodušit selenium :)
- [paypal-express](https://packagist.org/packages/kdyby/paypal-express) jsem použil na jednom webu a od té doby na něj nesáhl



## Call to action (or for help?)

Vím, že bootstrap-form-renderer a forms-replicator mají celkem dost uživatelů a jsou na hodně projektech, byla by proto škoda, kdyby úplně umřely. Stejně jako další projekty o které se nemůžu starat.

Na druhou stranu se mi nechce portovat renderer na Nette 2.2, protože tam David předělal nativní renderer a jde s bootstrapem dvojkou i trojkou snadno zkombinovat. Ze stejného důvodu se mi nechce renderer přepisovat aby podporoval trojku (což je navíc úplně jinej css framework a nemá s dvojkou nic společného kromě názvu).

Chtěl bych proto vyzvat uživatele mých knihoven, kteří je aktivně používají a vědí, že je ještě nějakou dobu používat budou, aby pokud mají zájem se mi ozvali a zkusíme se domluvit na spolupráci. Rád dám maintainera schopnému programátorovi, který se o knihovnu bude dobře starat, s mou pomocí. Přece jenom už mi to malinko začíná přerůstat přes hlavu a to mám ve frontě dalších >6 rozšíření, které jsem ještě ani nepublikoval na githubu.
