---
layout: blogpost
title: "Čistý projekt a spokojený zákazník"
permalink: blog/cisty-projekt-a-spokojeny-zakaznik
date: 2014-05-19 19:30
tag: ["Čistý kód"]
---

Sen každého programátora, že? Jenže sny se né vždy plní, proč to tak je?

Nejčastějším důvodem proč projekt, na kterém zrovna teď pracujete, není čistý, je ten důvod, že jste podlehli bludu, že *nabastlit aplikaci je rychlejší než napsat ji čistě*.

Z prototypů se stávají produkční verze dřív než stačíte otevřít pusu a zakřičet "proboha ne!", zákazník/zaměstnavatel tlačí na pilu a vy ve stresu podléháte a necháváte se donutit odvádět špatnou práci.
Z toho pak pramení **hromada bugů**, které je potřeba dokola fixovat, až se aplikace dostane do stavu, kdy je **neudržovatelná**, programátoři od projektu utíkají a aplikace i bussines se hroutí.

Ovšem má to i druhou stranu mince, aby psaní aplikace čistě probíhalo stejně nebo srovnatelně rychle jako bastlení, musí už mít programátor pár takových aplikací za sebou, 
nebo být alespoň v týmu s někým kdo ho povede a pohlídá.

Co znamená psát aplikaci čistě? Tak, aby se dobře vyvíjela a ještě lépe udržovala? Je potřeba:

- použít MVC (né vždy, ale v 99% případů u webových aplikací je to must-have)
- použít rozšířený framework (komunita, podpora, doplňky)
- psát testy (alespoň integrační)
- <a href="https://en.wikipedia.org/wiki/SOLID_(object-oriented_design)">SOLID</a>
	- Single responsibility
	- Open-closed
	- Liskov substitution
	- Interface segregation
	- Dependency inversion
- [DRY](https://en.wikipedia.org/wiki/Don't_repeat_yourself)
- [KISS](https://en.wikipedia.org/wiki/KISS_principle)

To bychom měli jako takový buzzwordový základ. Ale není to vůbec málo, že?


## Hlavní jsou peníze

<blockquote class="twitter-tweet" lang="en"><p>3 goals of successful software development: 1) code works 2) code is easy to understand 3) code is easy to change. Nothing else. Easy right?</p>&mdash; Piotr Solnica (@_solnic_) <a href="https://twitter.com/_solnic_/statuses/464014158187741184">May 7, 2014</a></blockquote>

Váš úkol číslo jedna, jakožto programátora, je napsat aplikaci, která bude **vydělávat peníze**.
Protože jinak vaše práce nemá smysl (pokud tedy neděláte pro neziskovku nebo za dotace), s tím se prostě smiřte.

Jenže s tím, aby aplikace vydělávala peníze, souvisí všechno ostatní:

- Je v zájmu zákazníka/zaměstnavatele, aby vaše aplikace nebyla nabastlená a měla testy, protože jinak nepůjde snadno měnit
- Když nepůjde snadno měnit, konkurence vás předhoní a začne vám přebírat zákazníky
- Když nemáte zákazníky, vaše existence nemá smysl, firma umírá
- Šéf na vás chodí řvát že je to vaše vina
- Vyhoříte, podléháte težkým depresím, zůstávají po vás jenom sirotci

Hrůza, co? A přitom stačilo psát aplikaci čistě ;)

Je vašim úkolem, jakožto programátorů, odvádět dobrou práci. Takovou práci, o které víte, že je **ve prospěch firmy**.
A taky je vašim úkolem tohle vysvětlit svému nadřízenému, pokud to ještě neví nebo nechápe.

Ekonomika projektu je prostě **to nejdůležitější**. Je to bohužel také ultimátní argument,
když něco neumíte naprogramovat čistě tak, aby vývoj netrval měsíce, když byste to zvládli nabastlit za třetinový čas,
tak se vaše stanovisko strašně špatně obhajuje a skoro nikdy vám to neprojde, v momentě kdy na projektu pálíte jiné peníze než vlastní.

Samozřejmě taky záleží na velikosti aplikace. Když budete dělat web pro lokální kavárnu,
tak si jen stěží obhájíte výdaje na psaní testů, CI server, redundantní prvky v infrastruktuře, loadbalancery, aws...
Ale ono by hlavně ani nemělo smysl o cokoliv takového se pokoušet, protože daný projekt nikdy nic takového potřebovat nebude.

Je potřeba se zamyslet nad tím co potřebuje projekt (nebo konkrétní feature) a umět si vždy obhájit způsob vývoje.
Případně dojít k nějakému kompromisu (šéfové/zákazníci nejsou vždycky jenom technologičtí ignoranti), ale na tom si skálopevně trvat.

Občas je taky potřeba udělat ústupek a některou část aplikace napsat méně robustní, v zájmu rychlosti implementace,
aby na dané feature mohla firma začít vydělávat peníze dříve. Když pracujete v nové doméně, o které moc nevíte, tak to někdy ani jinak nejde (typicky jakýkoliv startup).

Musíte ale počítat s tím, že *technologický dluh vás vždy dožene*. Zapomenutý index, nebo špatně navrhnuté tabulky v databázi,
na které po měsíci zapomenete, vás za další dva měsíce, až se vám skokově zvedne návštěvnost, kousnou do zadku a začnou vám třeba zabíjet databázi.
To se pak hodně špatně řeší, když v tabulce máte hromadu dat a musíte nejenom napsat novou implementaci, ale i přenášet stará data na novou strukturu, 
ideálně bez ztráty historie objednávek.

Když už uděláte ústupek, vždy se snažte takové problémy řešit před tím, než nastanou.


## TL;DR?

Je ve vašem zájmu a v zájmu vašich zaměstnavatelů, abyste se vzdělávali a byli schopní psát čistější aplikace.
Ušetříte peníze zaměstnavateli tím, že budete muset řešit méně bugů. Také zvýšíte svou cenu na trhu práce a svou produktivitu.

Snažte se psát čisté aplikace, trpělivě vysvětlujte nadřízeným výhody čistého kódu, trvejte na psaní čistého kódu (a testů),
ale hlavně to dělejte chytře a efektivně, ať vám to pak není vyčítáno. Jde to.
