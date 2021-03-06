---
layout: blogpost
title: "Testování konzistence dat"
permalink: blog/testovani-konzistence-dat
date: 2012-11-07 00:05
tag: ["Redis", "Testování"]
---

Objevil jsem docela zásadní bug ve svém `RedisExtension` pro Nette Framework. Poslední vyhrává. Zkuste si spustit 4 ajaxové requesty v jeden moment, které upravují session. Ty první 3 jako by nebyly, protože ten poslední všechny změny přepíše. Průser že? Co s tím?


## Zamykání klíče v Redisu

Oficální dokumentace doporučuje použít [SETNX](https://redis.io/commands/setnx). Je úplně jedno jakou datovou strukturu použijete. Redis prostě neblokuje a můžete si klidně dělat stojky na hlavě. Uspávat aplikaci a hádat se o zámek spamováním Redisu? Až jako poslední možnost.

Ovšem po zbytek článku (až ke komentářům) budeme předpokládat, že metodyku zamykání máme vyřešenou. Jak si ale ověříme, že funguje?


## Konzistence v PHPUnit

Narovinu, nic takového v PHPUnit **nejde**. S tím se ale přece nesmíříme!

Nejprve jsem si musel ujasnit, co vlastně chci dělat - chci spouštět kus kódu opakovaně a potřebuji aby se spouštěl ve více vláknech. Strávil jsem půl dne hledáním řešení v čistém PHPUnit. Annotace, podědění a upravení `TestCase`, nebo `TestSuite`. Nic, nikde na internetu ani ťuk, ale možná jen neumím hledat?


## Vlastní řešení

Nejrozuměji se to dá docílit nějak tak, jak to dělá `Nette\Tester`. Takže jsem se drobátko inspiroval a vypůjčil si dvě třídy. V mém případě to jsou `Process` a `ParallelRunner`. Když předám runneru název scriptu, tak mi ho 100x spustí ve 30ti vláknech (není problém navýšit).

Vymyslel jsem si také, že chci aby se to pěkně používalo.

~~~ php
public function testConsistency()
{
    $sessionDir = TEMP_DIR;
    $this->threadStress(function () use ($sessionDir) {
        session_save_path($sessionDir);
        session_start();
        $_SESSION['counter'] += 1;
    }, 100);
    $this->assertEquals(100, $_SESSION['counter']);
}
~~~

Samozřejmě jsem si musel napsat vlastní parser na funkce, protože se nechci spoléhat na přítomnost `pcntl` rozšíření.

<blockquote class="twitter-tweet"><p>Zkoušeli jste někdy parsovat kód funkce/metody v <a href="https://twitter.com/search/%23php">#php</a>? Díky absenci "sloupce" začátku a konce definice, je to téměř nemožné. <a href="https://twitter.com/search/%23fail">#fail</a></p>&mdash; Filip Procházka (@ProchazkaFilip) <a href="https://twitter.com/ProchazkaFilip/status/265805680856932354" data-datetime="2012-11-06T13:19:47+00:00">November 6, 2012</a></blockquote>

Parsovat funkce a metody je ještě docela hračka, ale zkuste si to s closurama ;)

Tokenizerem projdu soubor a najdu všechny definice funkcí a metod a pomocí `ReflectionFunctionAbstract::getStartLine()` dokážu spárovat i closury (pokud jich není víc na jednom řádku).

Obsah closury vykopíruju a pomocí reflexe si přečtu hodnoty proměnných předaných přes `use()`. Přidám bootstrap z testů a nějaké `use` tříd na začátek souboru a všechno slepím do jednoho scriptu. Takový script už se dá krásně spouštět pomocí `ParallelRunner`.

Výsledek je funkční testování kódu v desítkách vláken s elegantním zápisem. Jenom to trochu žere (nečekaně) a už se to nedá považovat za unit test ;)


## Na závěr zpět k Redisu

Nevíte někdo, co s těmi zámky? Dočasně jsem úplně vypnul `RedisSessionHandler`, protože je teď tak trochu k ničemu. Pokud na nic nepříjdu, asi přistoupím na řešení s `SETNX`, ale moc se mi do toho nechce.
