---
layout: blogpost
title: "Debuggujeme s PhpStormem"
permalink: blog/debuggujeme-s-phpstormem
date: 2012-12-20 16:20
tag: ["PhpStorm", "Xdebug", "PHP"]
---

Nejprve si [nainstalujeme a nastavíme Xdebug](https://xdebug.org/docs/install), pomocí [pecl](https://pecl.php.net/), který by měl být součastí všech instalací PHP.

~~~ shell
$ sudo pecl install xdebug
~~~

Dále nás zajímá, odkud bere PHP konfiguraci

~~~ shell
$  php -i |grep ini
Configuration File (php.ini) Path => /usr/local/lib
~~~

V mém případě složka obsahuje několik `.ini` souborů

~~~
$ ls /usr/local/lib |grep php
php-cli.ini
php-fpm.ini
php.ini
~~~

Do všech těchto souborů zkopírujeme následující řádky na úplný konec (většinou jsou nutná root práva).

~~~ ini
[xdebug]
zend_extension=xdebug.so

xdebug.remote_enable=1
xdebug.remote_connect_back=On
; xdebug.remote_host=127.0.0.1
; xdebug.remote_port=9001
xdebug.remote_autostart=1
xdebug.remote_log="/var/log/php/xdebug.log"
xdebug.idekey=PHPSTORM
; xdebug.profiler_enable=1
; xdebug.profiler_output_dir=/tmp/xdebug-profiler
~~~

Tohle nastaví Xdebug na velice agresivní režim. Na zbytečné rozšíření do prohlížeče (pokud jste nějaké používali) zapomeňte, nejsou potřeba - ukážeme si za moment.

Může se nám také stát, že nějaká aplikace nebo služba bude sedět na portu 9000, který je standardní pro Xdebug - od toho je tu `xdebug.remote_port`.

Každý operační systém má konfiguraci trošku jinak. Pokud máte Xdebug již nainstalovaný a jste zvyklí ho konfigurovat jinak, tak nejdůležitější jsou tyto volby.

~~~ ini
xdebug.remote_enable=1
xdebug.remote_connect_back=On
xdebug.remote_autostart=1
~~~

Nezapoměňte restartovat apache, nebo php-fpm ;)


## Konfigurace PhpStorm

Každý projekt by měl mít nastavený, jakou verzi jazyka používá a cestu k interpreteru.

![phpstorm-php-interpreter-settings](/content/phpstorm-php-interpreter-settings.png)

A taky je nutné, aby port souhlasil s nastavením Xdebugu v `php.ini`. Já používám port 9001, ale 9000 je výchozí a pokud nastavení neměníte, nemusíte tuto nabídku vůbec otevírat.

![phpstorm-php-debugger-settings](/content/phpstorm-php-debugger-settings.png)


## Spustění debuggeru

Kliknutím vedle čísla řádku (tam kde je teď červená tečka) vytvoříme tzv. breakpoint. Tj místo, kde se provádění aplikace zastaví a my budeme moct zkoumat stav proměnných a krokovat.

![phpstorm-php-debugger-basePresenter](/content/phpstorm-php-debugger-basePresenter.png)

Protože jsme Xdebug nastavili na agresivní mód, tak při úplně každém požadavku bude zkoušet vytvořit spojení. Následující kouzelnou ikonkou řekneme PhpStormu, že má na tato spojení začít přijímat.

Když je telefónek zelený, tak naslouchá. Nevím proč, ale vždycky mě to strašně mate a musím kouknout na titulek...

![phpstorm-php-debugger-listen](/content/phpstorm-php-debugger-listen.png)

Nyní stačí otevřít náš projekt v prohlížeči, nebo obnovit stránku.

Poprvé se nás zeptá, jestli má spojení příjmout a pokud mu to povolím, příště se ptát nebude.

![phpstorm-php-debugger-askAccept](/content/phpstorm-php-debugger-askAccept.png)


## Hodnoty proměnných, stacktrace, krokování, ...

Po spojení se otevře nový panel.

![phpstorm-php-debugger-opened](/content/phpstorm-php-debugger-opened.png)

1. Může zabít běh, nebo spustit pokračování `F9` až do konce scriptu, nebo do dalšího breakpointu.
2. Nástroje na krokování scriptu
    1. Otevře soubor, ve kterém Xdebug čeká (například když si zavřeme soubor s třídou a nevíme, kde jsme skončili)
    2. Přeskočit další výraz `F8` - výraz se vykoná, ale "na pozadí""
    3. Vstoupit do dalšího výrazu `F7` - například když volám nějakou svou funkci, tak debugger krokuje i její obsah
    4. Násilně vstoupit do dalšího výrazu
    5. Vyskočit z funkce (souboru) `Shift+F8`
    6. Pokračovat vykonávání až ke kurzoru
3. Stack trace ukazuje zanoření funkcí a metod, jak byly volány. Například je výborné, že se můžete posunout o úroveň výš a kouknout na proměnné v předchozí funkci. Program se nikam neposouvá, pouze debugger zobrazuje jiný kontext programu.
4. Proměnné v aktuálním kontextu - zde si můžeme detailně prohlédnout obsah proměnných a dokonce ho měnit!

![phpstorm-php-debugger-variables](/content/phpstorm-php-debugger-variables.png)


## Eval

O evalu se říká, že je zlý, ale tento eval je hodný :) Zkratkou `ALT+F8` otevřete okno, do kterého můžete psát PHP kód a nechat ho vykonat v aktuálním kontextu scriptu. Velice často si například vložím breakpoint, kde píšu nějaký regulární výraz a ladím ho v tomto okně, dokud mi nevyhovuje jeho výsledek.

![phpstorm-php-debugger-evaluateExpr](/content/phpstorm-php-debugger-evaluateExpr.png)


## V testech

Za naprostou killer feature považuji debugování testovacích metod, když si nastavíte dobře PhpUnit.

Vložím do testu breakpoint, pravým tlačítkem hlodavce otevřu nabídku a zvolím "Debug ..."

![phpstorm-php-debugger-tests](/content/phpstorm-php-debugger-tests.png)

A opět můžu studovat obsah proměnných, měnit jejich hodnoty a volat vlastní funkce.

![phpstorm-php-debugger-tests-running](/content/phpstorm-php-debugger-tests-running.png)


## Pointa: Přestaňte používat textové editory,

když to co potřebuje je IDE! Sublime text je sice hyper cool textový editor, ve kterém zrovna píšu i tenhle článek, ale přestaňte se už mučit. Kód není text.

PhpStorm můžete [používat první měsíc zdarma](https://www.jetbrains.com/phpstorm/) ;)
 A pokud se vám nebude líbit, je tu pořád ještě [NetBeans](https://netbeans.org/) a [PhpEd](https://www.nusphere.com/products/phped.htm).
