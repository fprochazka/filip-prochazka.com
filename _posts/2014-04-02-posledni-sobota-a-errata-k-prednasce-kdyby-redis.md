---
layout: blogpost
title: "Poslední sobota a errata k přednášce Kdyby/Redis"
permalink: blog/posledni-sobota-a-errata-k-prednasce-kdyby-redis
date: 2014-04-02 00:30
tag: ["Nette Framework", "Cache", "Kdyby", "Redis", "PHP"]
---

Na [Pražské březnové posobotě](https://forum.nette.org/cs/17090-posledni-sobota-59-csfd-praha-29-3-2014") jsem měl přednášku o [Kdyby/Redis](https://github.com/Kdyby/Redis/blob/master/docs/en/index.md), zde jsou slajdy s komentáři:

<iframe src="https://www.slideshare.net/slideshow/embed_code/33007726" width="427" height="356" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" style="border:1px solid #CCC; border-width:1px 1px 0; margin-bottom:5px; max-width: 100%;" allowfullscreen> </iframe>

Kamera byla, takže až to [Patrik](https://twitter.com/PatrikVotocek) zpracuje, bude i video


<iframe width="640" height="360" src="//www.youtube.com/embed/CzsyXM935GM" frameborder="0" allowfullscreen></iframe>



## Proč errata?

Přednášku jsem si jako vždy připravoval den předem a během přípravy jsem se rozhodl udělat nějaké jednoduché benchmarky.
Naprosto mi ale padla čelist, když jsem zjistil že filesystem mám na localhostu rychlejší než RedisStorage.
Pojal jsem to statečně a rozhodl se vyzvat publikum, aby mi to pomohlo vyřešit.

Po přednášce jsme měli plodnou diskuzi a kluci se mi vysmáli, že ukládám cache doctrine metadat a annotací do Redisu.
Protože používáme nejnovější stable PHP (tedy 5.5.něco) žil jsem v mylné představě že APC je mrtvé a tedy nad tím nemusím vůbec přemýšlet.
Jenže! Ono není tak úplně mrtvé a nepoužitelné jak jsem si myslel.

Ještě během [Davidovy](https://twitter.com/geekovo) přednášky jsem nainstaloval [APCu](https://pecl.php.net/package/APCu),
tedy uživatelskou cache z APC (ta část která neztratila smysl existence) 
a vylepšil [Kdyby/Annotations](https://github.com/Kdyby/Annotations) aby na nich šla lépe konfigurovat cache.

Nakonec jsem tedy cache annotací a metadat přesměroval do apcu a místo ~10000 (slovy: deseti tisíců) requestů
na prvnotní inicializaci stránky s kompilací containeru a načítání doctrine metadata jsem se dostal na špičkových ~200 requestů do Redisu.
Na průměrnou stránku, která už má vygenerovanou cache mi požadavky z původních minimálně 200 spadly na ~80.

Vytížení Redisu víc než o polovinu padlo, aplikace se nepatrně zrychlila
a zatím se ani jednou nezasekla na generování cache (což byl předtím problém).
Strašák shardování se odkládá na neurčito :)

*PS: [Juzno](https://twitter.com/juznacz), dlužíš mi ještě to vysvětlení, jak udělat konzistentní hashování klíčů do shardování, které nebude potřeba přehashovávat ani při přidání dalších instancí.
Teď [to dělám takto](https://github.com/Kdyby/Redis/blob/c86023d887f2cbf75068c71d1a3c00123b1ad682/src/Kdyby/Redis/ClientsPool.php#L66-L69.*)


## Takže ještě jednou závěr

Nad použitím session storage z Kdyby/Redis není třeba vůbec přemýšlet a prostě ji použijte, vyplatí se vždy.
A ikdyž je cache malinko pomalejší než jsem doufal (požadavky do 0,3ms na request včetně overheadu mého storage),
pořád je brutálně rychlá oproti filesystému pod zátěží.
