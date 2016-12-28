Date: 2015-07-17 18:55
Tags: Nette

# Opensource v českých podmínkách

Možná jste zaregistrovali

- [Devel: Jak přimět větší firmy podporovat open source](http://devel.cz/otazka/jak-primet-vetsi-firmy-podporovat-open-source)
- [Nette fórum: Jak přimět větší firmy podporovat open source](http://forum.nette.org/cs/23585-jak-primet-vetsi-firmy-podporovat-open-source)
- [Nette fórum: Nette Pro: zatím asi nejschůdnější budoucnost pro Nette](http://forum.nette.org/cs/23770-nette-pro-zatim-asi-nejschudnejsi-budoucnost-pro-nette)

David Grudl, tvůrce Nette Frameworku zkrátka řeší, jak se dostat do stavu, že bude Nette živit jeho a né naopak.

Na tom není nic divného, mám desítky nápadů co bych chtěl udělat do [Kdyby](https://github.com/kdyby), ale zkrátka na to není čas.
Přesněji, není to pro mě priorita. Priorita je pro mě zaplatit nájem, mít na jídlo a občas si koupit nějakou novou hračku. A až potom můžu dělat dokumentaci do Kdyby.
[Kdyby](https://github.com/kdyby) mě totiž neživí. Ohromným způsobem mi pomohlo rozvinout se, získat kontakty a lepší práci, ale neživí mě.

Troufl bych si tvrdit, že ta situace je velice podobná, ovšem v trošku jiném měřítku. Nette je konec konců 1000x větší projekt.



## Jak jsme se dostali do současné situace

Tahle část článku bude extrémně cynická, brutálně upřímná a možná trochu chaotická (protože mám maličko problém zasadit události na správné místo v čase).
Moc si přeju, aby se David neurazil, ale odnesl si z toho něco pozitivního.
Nebudu popisovat realitu (protože neznám všechny detaily), ale svůj úhel pohledu.

Před >10 lety začal David pracovat na něčem, co mu mělo usnadnit práci.
Protože už tou dobou pěkně blogoval, tu a tam se o něčem pochlubil, tu se o něco podělil a zjistil, že je o to velký zájem.
Zájem o to byl, protože David je geniální programátor, to mu nikdo neodpáře.
Z nějakého důvodu se rozhodl Nette otevřít jako opensource. A od té doby na něm nezpochybnitelně udělal práci v hodnotě miliónů korun.

Protože lidé Nette používali, tak ho začal školit.
Tady by normálně končila pohádka o úspěšném opensource jako velké vítězství pro autora.
Vytvořím skvělý nástroj => hodně uživatelů => vydělám na školení => repeat.

Pak se v Davidovi ovšem něco zlomilo a uvědomil si že mu to nestačí.
Že vlastně všechno kolem nette z 98% buduje sám a komunita mu dostatečně nepomáhá.

<blockquote class="twitter-tweet" lang="en"><p lang="sk" dir="ltr">Bylo to rozhodně velmi zajímavých 10 let! A je čas se posunout dál. Končím s vývojem Nette Frameworku.</p>&mdash; geekovo (@geekovo) <a href="https://twitter.com/geekovo/status/417869320677367808">December 31, 2013</a></blockquote>

Což je částečně pravda. Komunita se snažila opakovaně za ty roky zorganizovat alespoň napsání dokumentace.
Vždycky to ztroskotalo na tom, že se to nedokončilo. Pokusy s "dokumentačním projektem" se opakují minimálně jednou do roka.

Největší věci co se dokončily, tak vždy udělal nějaký jednotlivec který to prostě hecl.
Honza Smitka napsal před X lety první velký tutorial na Nette co si tak pamatuju (asi se psalo i předtím, ale tam mě moje paměť zrazuje, takže se omlouvám, pokud jsem na někoho zapomněl... už jsou to roky!).
Před pár lety Honza Doleček vyhecoval další dokumentační kroužek u něj v bytě, díky čemuž vznikl nový (a zároveň i současný) quickstart.
A samozřejmě se pak našlo ještě pár jednotlivců, kteří také udělali kus práce.

Addons portál vznikl velice podobně, hecli jsme to v osmi lidech, zavřeli se na víkend do jedné místnosti a programovali.
To že to pak přes rok leželo ladem a nebyli jsme schopní to dotáhnout a nasadit je už další story.
Tohle pokud si dobře pamatuju hecl především Patrik Votoček, který dotáhl chybějící věci do konce a díky tomu se to mohlo pak konečně nasadit.

A někdy před rokem začal Honza Černý hecovat Poslední soboty a úplně extrémně to oživil. Dal tomu formu a nastavil vysokou laťku.
Já se pokoušel taky zorganizovat pár posledních sobot a vím moc dobře jakej je to stres a kolik je s tím práce.
Samozřejmě nesmím zapomenout na Vítkovy poslední soboty, které byly taky super.

Nebo takovej Milo, který na sebe vzal správu serverů a skvěle se stará o Nette\Tester. Taky jeden z mála lidí, kteří tohle zvládají fakt skvěle.

Z toho mi prostě vyplývá, že chyběla schopná osoba, která by to dobře zorganizovala.
Nahnat deset ajťáků do jedné místnosti umí každý, ale né každý je dokáže nadchnout a nasměrovat, aby něco konkrétního vytvořili.

Všechny Davidovy pokusy zapojit komunitu vždy probíhaly stylem

- Nechcete se taky zapojit ať to nedělám všechno sám?
- Ok, s čím chceš pomoct?
- Tady tohle a tohle by bylo fajn udělat
- Ok, tak já to někdy zkusím udělat až budu mít čas.
- Tak dík.

A samozřejmě se nikdy nic neudělalo, pokud to zrovna toho člověka nepálilo. A když se na něčem začalo dělat, tak se to nedotáhlo. **Protože to nikdo neřídil**.
Jedinej člověk, kterej dokázal konzistentně dotahovat velký věci bez odkládání vždy dřív než za půl roku až rok, je opět Chemix, kterej to má zjevně v krvi.

Všichni to známe, dotáhnout něco do konce sám od sebe chce vizi a extrémně pevnou vůli.
Já když nemám jasnou vizi, tak se vždycky zasekám na tom, že začnu řešit nějakou drobnost 10 hodin a pak už nemám sílu řešit ten hlavní úkol.



## Nette Internals

Ze stejného důvodu podle mě skončily i Nette internals.
Upřímně se přiznám, že si nevzpomínám, že bych sám od sebe zvládl něco konkrétního pro Nette za internals udělat.
Matně si pamatuju že jsem pomáhal Martinu Majorovi s nějakým pullrequestem, ale už si nevzpomínám jak to dopadlo.

Na druhou stranu, nemám z toho ani ždibíček černé svědomí.
Netvrdím že všechny mé příspěvky byly dokonalé, ale [115 pullrequestů](https://github.com/pulls?utf8=%E2%9C%93&q=is%3Apr+author%3Afprochazka+org%3Anette+) podle mě není málo.
Stejně tak netvrdím, že jsem vždy dával dokonalé rady, ale [přes čtyři a půl tisíce příspěvků](http://forum.nette.org/cs/userlist.php?username=&show_group=-1&sort_by=num_posts&sort_dir=DESC&search=Odeslat) taky není málo.
Nebo těch [39 rozšíření](https://packagist.org/packages/kdyby/) pro Nette, to mi taky nepřijde jako málo.

Rozhodně se tím nesnažím říct, že já už mám svůj "morální dluh" za všechno co mi Nette přineslo splacenej, to zdaleka ne.
Ale prostě už mám po těch letech problém jen tak si o víkendu sednout nad editor a začít třeba psát dokumentaci k Nette.
Raději budu psát dokumentaci ke Kdyby, nebo opravovat svoje bugy, které mi například brání v práci.
Nebo prostě taky nebudu dělat nic, to je taky strašně krásná věc, kterou jsem objevil během posledního roku.

Chci tím říct, že úplně stejný "problém" mají úplně všichni co používají úplně jakýkoliv opensource.
Existují tisíce nástrojů, každý maličko jiný a jeden si z té řady vyberu.
Naučím se ho používat a používám ho na věci, které mi vydělávají peníze.
Když je v něm bug, tak v lepším případě se ho pokusím opravit a pošlu pullrequest (což spousta lidí třeba ani neumí).
V horším případě si bug opravím jen pro sebe, protože nemám čas ani sílu řešit začlenění zpátky do toho nástroje.
V úplně nejhorším případě (pro balíček) ho prostě vyměním za jiný, který ty problémy nemá.



## Vychovávání komunity

Což mě přivádí k dalšímu, naprosto zásadnímu problému, který David dlouhá léta ignoroval.

<blockquote class="twitter-tweet" lang="en"><p lang="sk" dir="ltr"><a href="https://twitter.com/spazef0rze">@spazef0rze</a> <a href="https://twitter.com/HonzaMarek">@HonzaMarek</a> A co mi to přinese? Konkurence umí peníze sehnat lépe, pull requesty přijímá vřeleji a dokumentaci má lepší...</p>&mdash; Jakub Kulhan (@jakubkulhan) <a href="https://twitter.com/jakubkulhan/status/621657646513422336">July 16, 2015</a></blockquote>

Kdo si vzpomene před rokem, dvěma, jak probíhalo posílání a začleňování pullrequestů? Správně, nijak.
Když se objevilo něco, co se vzdáleně dalo použít, tak to David cherrypickl, opravil a pushl do masteru. Když měl dobrou náladu tak poděkoval.
Co je na tom špatně? Samozřejmě všechno. Skoro nikdy dotyčnému nezačal vysvětlovat, že nedodržel coding style a tahle metoda je nevhodně pojmenovaná, a že by bylo fajn kdyby napsal test, se kterým by mu poradil, že se píše tak a tak.
Na druhou stranu, o tom jak je těžké se starat o pullrequesty, by se daly psát knížky.

Kdyby tohle dělal od začátku, má teď armádu "vychovaných" vývojářů, kteří umí posílat pullrequesty, které může bez okolků mergovat.
Stejně tak by měl armádu vývojářů, kteří přesně vědí co nováček udělal v pullrequestu špatně a proč ho David nemergne a poradí mu jak ho opravit, bez toho aby David musel napsat jediný komentář.

Já to třeba do teď neumím. Možná je to **moje** neschopnost, ale že by mi David něco mergl bez komentářů se stalo možná jednou nebo dvakrát (ze 115).

Na druhou stranu, v tomhle se poslední ~2 roky o několik řádů zlepšil a je vidět, že se snaží více komunikovat.



## Všechno je o penězích

No a hlavně, David do teď ani jednou **neřekl přímo**, že chce být placený **za vývoj** Nette. Nepamatuju si, že by to jednou jedinkrát někde zmínil nebo někam napsal.
Udělal to poprvé [až tady](http://forum.nette.org/cs/23770#p159589) (všimněte si drastického rozdílu reakcí komunity na tento příspěvek a na ten první ve vláknu).

Na Nette chatu mi bylo jedním člověkem vyčteno, že ti kteří se zajímají, tak to věděli. V tom případě **jsem ignorant**, stejně jako naprostá většina komunity. Čest výjimkám.

Moje logika byla prostá: David školí Nette => má z toho peníze => rozvíjí Nette aby mohl dělat více školení více lidem a mít z toho více peněz => repeat.
Asi jsem pořád ještě malej kluk, ale tohle mi přijde jako snová práce z pohádky.
Ovšem netvrdím, že se na tom napakoval a máme nárok na to, aby dál tvořil Nette za svoje, to ani v nejmenším.

Žijeme v kapitalismu a sice pár lidí pošle příspěvek, ale dokud nebudou mít firmy konkrétní vidinu zisku, kterou jim přinese pravidelný příspěvek, tak to prostě dělat nebudou.
Proč by to taky dělaly? Když můžou jít a použít třeba Symfony, u kterého jim nikdo nebude vyčítat, že dostatečně nepřispívají a že autorovi je zle z toho jak musí žebrat o každou korunu...

Je sice strašně fajn a sluníčkový poslat peníze nějaké neziskovce, která se stará o děti na vozíčku,
ale která firma tohle udělá bez toho, aniž by si to mohla odečíst z daní nebo z toho měla PR?

A hlavně, ukažte mi jedinej projekt, kde tohle aktivně začala řešit komunita, bez toho, aby si hlavní autor sám řekl, že chce peníze.



## Jak z toho ven

Kritizovat umí každý a já jsem teď kritizoval možná až moc. Takže se pokusím pozitivně příspět tím, že nabídnu jak bych situaci řešil já.

V první řadě by bylo fajn, kdyby David přestal vyhrožovat tím, že framework zabije (což sice tvrdí že nedělá, ale všichni tak současnou situaci chápou, a kdo ti tvrdí opak Davide, tak ti lže).
Nikdo nepošle ani korunu na projekt, kterýmu někdo drží kudlu u krku.
Aniž bych to vyhledával tak jsem zaregistroval několik desítek lidí, kteří na to okamžitě zareagovali tak, že začali hledat srovnání Nette se Symfony, Laravelem a dalšímy PHP frameworky.

Naprosto zásadní je, místo výpalného vybírat příspěvky na další rozvoj.
Nette zdaleka ještě není v situaci, kdy by si mohlo dovolit mít komerční licenci a nebo dokonce dokumentaci jen pro platící zákazníky. 

<blockquote class="twitter-tweet" data-partner="tweetdeck"><p lang="sk" dir="ltr">Každopádně, dvojí licence posune <a href="https://twitter.com/hashtag/nettefw?src=hash">#nettefw</a> do sféry korporátu nebo ho zabije. Ani jednoho scénáře se účastnit nechci.</p>&mdash; Filip Procházka (@ProchazkaFilip) <a href="https://twitter.com/ProchazkaFilip/status/621395242609192960">July 15, 2015</a></blockquote>

Žádnej novej vývojář, kterej se za dva roky od teď vypracuje na pozici, kde rozhoduje o tom v jakém frameworku se napíše další projekt,
si nikdy nevybere placené Nette, kterému ve free verzi "něco chybí", když může mít Symfony, které je zadarmo a "celé".
Tohle bude fungovat pouze a jenom v korporátu a pouze a jenom když k tomu bude garantovanej support, kterýmu se David zjevně brání.

Kompletně bych přepracoval donate na "[Patreon model](https://www.patreon.com/about)".
- Musí existovat stránka, kam já jako vývojář přijdu a uvidím, že David potřebuje měsíčně XY tisíc na to, aby se mu vyplatilo dál rozvíjet Nette a mohl na něm dělat třeba na půl úvazku.
- Musí to být transparentní a musí být vidět kolik už vybral a kdo mu ty peníze dal (PR pro firmy na hledání zaměstnanců?)
- V ideálním světe to půjde odečíst z daní jako dar neziskovce.
- Musí být jasně stanoveny **nové** features, které díky příspěvkům vzniknou (motivace pro programátory jít za šéfem a říct o příspěvek)
- Musí být jasně řečeno, že když se vybere o tolik a tolik víc, bude možné najmout někoho kdo bude dávat dokupy dokumentaci atd (motivace vybrat víc než drobnou charitu)
- Musí tam být, že programátor, který to právě čte má jít za svým nadřízeným a přesvědčit ho pomocí argumentů, které mu dá ta stránka, že by mohli začít posílat aspoň 1-2 tisíce měsíčně (nebo víc) na framework, který jim ulehčuje práci.
- Když už nemá motivaci posílat firma, tak alespoň namotivovat toho programátora/freelancera, ať pošle aspoň něco. Ale né vytvářením viny, ale vidinou pozitivního příspěvku který mu přinese nové features.
- Musí pravidelně na Nette blogu vznikat report o tom, že se fakt něco děje a David fakt pracuje (naprosto zásadní pro úspěch). Takhle totiž funguje jakékoliv normální fulltime zaměstnání, že ten co někomu platí, tak má přehled o tom co se udělalo a co ne. A taky proč se to neudělalo.
- Když už se něco udělá, musejí se naplánovat a zpropagovat nové věci, na kterých se bude dělat další měsíc, třeba.

Když se tohle udělá, tak to [není žádné žebrání](http://www.latrine.cz/drzet-hubu-sem-tam-spitnout). Je to úplně obyčejná výměna služeb.
Stránka se může zpropagovat na rootu, zdrojáku, lupě.
Evangelisti si můžou hodit ribon na weby a twítnout výzvu.
Jestli má někdo na to tohle v ČR dokázat, tak je to David s Nette.
Fukovi to [taky funguje](http://fuxoft.cz/fffilm/ffffriends/?alltime=yeah) a to jeho blog jen těžko někomu generuje zisk.

Nic z toho nemusí udělat David, můžeme to klidně udělat nějaký víkend (nebo dva) jako komunita.
Nemůžeme to ale udělat bez tebe Davide, musíš chtít ty sám a dovolit nám to.

Jsou samozřejmě možné i další modely.
- Placené screencasty (když z nich budu mít share, rád zkusím nějaké točit), viz laracasts.
- Nějaká forma placené podpory - tímhle se živí >90% všech opensource, které mají **funkční** bussines model.
- Někdo měl super nápad s placenou LTS pro starší verze, na kterých mají firmy zakonzervované aplikace, které se nevyplatí upgradovat.
- Pokud by David sehnal ty dva nebo tři patrony z velkých firem, které budou třeba na půl roku sponzorovat Nette tak by to bylo taky super.

Každopádně, je potřeba si uvědomit, kde se staly chyby, aby se už neopakovaly. Jinak to tu za rok máme zase.

**Pojďme spolu "zachránit" Nette.**

![drop-the-mic](/content/drop-the-mic.gif)
