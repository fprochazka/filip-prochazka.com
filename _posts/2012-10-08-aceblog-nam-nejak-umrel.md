---
layout: blogpost
title: "Aceblog nám nějak umřel"
permalink: blog/aceblog-nam-nejak-umrel
date: 2012-10-08
tag: ["Publikace"]
---

Už je to docela dlouho, "co nám hackli Aceblog"((teda myslím že ho hackli... ach ten wordpress!)) a mně tam visí články ve vzduchu. Sice už nejsou nejaktuálnější, ale stejně by mě mrzelo, kdyby zmizely v propadlišti dějin. Patrik mi proto poskytl zálohu a zveřejňuji je zde.

Samozřejmě se pár věcí od vydání změnilo. 


## "Článek: Začněte testovat":/blog/zacnete-testovat

- Už skoro rok nepoužívám NetBeans, ale PhpStorm, který je na mé platformě výrazně rychlejší.
- V článku zmiňuji soubor `tests/phpunit.xml`. Lepší konvence je mít jej v rootu projektu.
- Samozřejmě používám novější PHPUnit.
- Testování databáze jsem [od té doby párkrát zrefaktoroval](https://github.com/Kdyby/Framework/tree/master/libs/Kdyby/Tests). Například jsem zjistil, že sdílení jednoho připojení je hodně špatný nápad, byť je to zatraceně rychlé. Vznikaly totiž těžko odhalitené chyby. Povedlo se mi ovšem optimalizovat inicializaci Doctrine tak, že vytvářím schéma a proxy třídy pouze pro entity, které budou použity v testu.


## "Článek: Komponenty pomocí Dependency Injection":/blog/komponenty-pomoci-dependency-injection

- Vykreslování komponent pomocí předávání parametrů render metodě `{control article $presenter->id}` je zlo. S tím souvisí, že mít více jak jednu `render` metodu je také zlo. Někdy si vysvětlíme proč.


## "Článek: Doctrine a service vrstva aneb takto mi to dává smysl":/blog/doctrine-a-service-vrstva-aneb-takto-mi-to-dava-smysl

- [Na metadata](https://github.com/Kdyby/Framework/blob/master/libs/Kdyby/Doctrine/Dao.php#L475), [na relace](https://github.com/Kdyby/Framework/blob/master/libs/Kdyby/Doctrine/Dao.php#L495) a taky na [reference](https://github.com/Kdyby/Framework/blob/master/libs/Kdyby/Doctrine/Dao.php#L427) jsem si přidal extra metody.
