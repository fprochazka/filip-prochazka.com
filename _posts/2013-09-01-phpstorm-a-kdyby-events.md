---
layout: blogpost
title: "PhpStorm a Kdyby/Events"
permalink: blog/phpstorm-a-kdyby-events
date: 2013-09-01 20:00
tag: ["Nette Framework", "PhpStorm", "PHP", "Kdyby"]
---

Používáte rozšíření [Kdyby/Events](https://github.com/Kdyby/Events) pro [Nette Framework](https://nette.org/)?
Mám pro vás skvělou zprávu, [juzna](https://twitter.com/juznacz) je používá taky a napsal rozšíření pro PhpStorm, které vám usnadní práci s tímto rozšířením!

[Zde si stáhněte rozsíření](https://github.com/juzna/intellij-kdyby-events/releases), které nainstalujete do IDE a můžete ho hned začít používat :)

<!--more-->
## Co to umí?

Koukněte se na definici eventů - u každého přibyla ikonka, pokud máte v projektu třídu, která na tento event naslouchá.

![phpstorm-events-go-to-usage](/content/phpstorm-events-go-to-usage.png)

Tato ikonka má dvě funkce, buďto při kliku rovnou skočí na listener, který naslouchá na tento event, nebo pokud jich je více, tak je vypíše a můžete si vybrat

![phpstorm-events-list-usages](/content/phpstorm-events-list-usages.png)

Samozřejmostjí je napovídání existujících událostí

![phpstorm-events-autocomplete](/content/phpstorm-events-autocomplete.png)

Třešiničkou je možnost prokliknout se na definici eventu

![phpstorm-events-go-to-event](/content/phpstorm-events-go-to-event.png)

Díky tomu že event je klikatelný, tak fungují i další funkce IDE, jako například [rychlá dokumentace](https://www.jetbrains.com/phpstorm/webhelp/viewing-inline-documentation.html)

![phpstorm-events-doc](/content/phpstorm-events-doc.png)

Pokud najdete nějakou chybku, tak to prosím nereportujte ke mně do komentářu, ale [založte issue na githubu](https://github.com/juzna/intellij-kdyby-events/issues), děkuji!
