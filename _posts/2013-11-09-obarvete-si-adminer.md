---
layout: blogpost
title: "Obarvěte si Adminer"
permalink: blog/obarvete-si-adminer
date: 2013-11-09 22:30
tag: ["PHP"]
---

Na desktopových klikátkách na databáze jste už určitě viděli obarvené záložky podle cíle spojení.

> ![adminer-colored_navicat](/content/adminer-colored_navicat.png)
> <small>Obarvené záložky v Navicatu</small>

Na co je to dobré? No tak například nemusíte číst název spojení a hned víte kde jste.
 Já barvičky použivám na odlišení "důležitosti" databáze - localhost/dev/produkce.

Jenže nepoužívám Navicat ale Adminer.



## Rychlokurz psaní pluginů pro Adminer

- [Stáhneme si Adminer](https://www.adminer.org/cs/#download).
- Stažený soubor přejmenujeme z `adminer-blabla.php` na `adminer.php`
- Vytvoříme si vedle Admineru složku `plugins/` a do ní [stáhneme soubor plugin.php](https://github.com/vrana/adminer/blob/master/plugins/plugin.php)
- Vytvoříme soubor `index.php` do kterého dáme následující kód a přes který budeme k Admineru přistupovat

~~~ php
<?php

function adminer_object()
{
    // required to run any plugin
    include_once __DIR__ . "/plugins/plugin.php";

    // autoloader
    foreach (glob("plugins/*.php") as $filename) {
        include_once "./$filename";
    }

    $plugins = array(
        // specify enabled plugins here
    );

    return new AdminerPlugin($plugins);
}

// include original Adminer
include __DIR__ . "/adminer.php";

~~~

Tohle je základní kostra. Teď si napíšeme náš plugin.



## Quick & dirty barevná schémata

Psaním tohohle článku jsem strávil více času než psaním následujícího rozšíření, takže to jde pravděpodobně udělat 2x elegantněji a 3x čistěji, ale to mě v tuto chvíli nezajímá, protože je to 10 řádků kódu které doufejme nikdy nebudou řídit žádnou banku :)


Na začátek souboru `index.php` vložíme následující třídu

~~~ php
class AdminerColors
{
    function head()
    {
        static $colors = array(
            // v tomhle poli si můžete zvolit barvy pro jednotlivé adresy
            '127.0.0.1' => '#d0fbcd',
            'localhost' => '#d0fbcd',
            'dev.kdyby.org' => '#fbf9cd',
            'www.kdyby.org' => '#fbd2cd',
        );

        if (!isset($colors[$_GET['server']])) return;

        echo '<style>body { background: ' . $colors[$_GET['server']] . '; }</style>';
    }
}
~~~

A do pole s pluginy vytvoříme novou instanci.

~~~ php
    $plugins = array(
        new AdminerColors,
    );
~~~

F5 a localhost už by měl chytnout nezdravou zelenou. Pokud by se vám zdálo, že je to hnusné jak noc, tak máte pravdu. Je to hnus :)

Proto je potřeba stáhnout můj [adminer.css](https://filip-prochazka.com/content/adminer.css) (stačí ho umístit do stejné složky jako je `index.php` a Adminer si ho sám načte), který fixuje ty největší průsery a kdyby se někomu zželelo nás graficky retardovaných programátorů a dodělal by tomu barevnej lifting, vůbec bych se nezlobil :)



## Výsledek

Pokud máte stejně jako já záchvaty paranoie a hrůzy z toho, že na produkci omylem smažete sloupeček, tak je tohle ideální řešení pro klidné spaní.


![adminer-colored_result](/content/adminer-colored_result.png)



## Jsem línej kopírovat...

Na [Githubu](https://github.com/fprochazka/adminer-colors) je [upravená verze, podle Ládi Marka](#comment-1116606531).
