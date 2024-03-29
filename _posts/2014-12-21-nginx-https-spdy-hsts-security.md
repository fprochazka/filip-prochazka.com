---
layout: blogpost
title: "Kompletní nastavení HTTPS, SPDY, IPv6 a HSTS pro Nginx"
permalink: blog/nginx-https-spdy-hsts-security
date: 2014-12-21 20:15
tag: ["NGINX", "Bezpečnost", "Linux"]
---

Následující článek rozhodně není "tohle je jediná správná cesta a takhle to dělejte".
Píšu ho hlavně proto, že za měsíc nebo za rok budu nasazovat HTTPS na další weby a budu zase googlit jak jsem to dělal před rokem :)

Nelekejte se délky, článek je ve skutečnosti krátký, jenom obsahuje hodně kódu a obrázků. Celé je to velice jednoduché :)

<!--more-->
## Motivace

V poslední době je to samá kauza s odposloucháváním od NSA- už tohle samo o sobě by mělo způsobit, že zbystříte a začnete se o bezpečnost na internetu zajímat. Ale pojďme si na chvíli představit, že státu je jedno co děláte ve svém volném čase a nemá zájem vám lézt do soukromí (haha). Jaká je motivace dát si na takovýto blog, který je dokonce z většiny statický, pořádný HTTPS certifikát a zablokovat kompletně http? Vždyť ani nemá přihlášení!

Když máte dobře nastavené HTTPS tak to zaručuje že

- nikdo nemůže **odposlechnout obsah komunikace**, tedy na veřejné wifi v kavárně se nemusíte bát přihlásit na facebook, protože vaše heslo nikdo z nich nemůže na síti odposlechnout
- není možné **změnit obsah webu** - kdo by tohle dělal? Kdokoliv kdo má rád peníze! Sedíte přece na veřejné wifi v kavárně a někoho by tam mohlo napadnout, že když vám dává zadarmo internet, tak by klidně mohl do stránek které si prohlížíte strkat reklamy, nebo je aspoň nahrazovat za ty svoje. A nemusí to být přímo ani ta kavárna, může to [udělat kdokoliv - třeba Michal Špaček na konferenci](https://youtu.be/0TX8fdhi6ck?t=18m55s). Stačí krabička za pár dolarů, proxy která přidá do stránky reklamu a sednout si do KFC a tvářit se jako veřejná wifi.

Tohle jsou prostě fakta - když nemáte HTTPS, tak vašim zákazníkům/čtenářům může někdo měnit po síti obsah webu, klidně i změnit obsah článku. A taková možnost je pro mě naprosto nepřijatelná, ať je jakkoliv nepravděpodobné, že by někdo měl potřebu dělat tohle zrovna mému blogu.

Nezapomínejme ale také na to, že **soukromá informace** je už jenom to, co si na nějakém webu prohlížíte.

Michal Špaček před pár dny obepsal velké eshopy na českém internetu a ptal se jich, co udělají se zabezpečením.
Přechod na HTTPS někdy nemusí být úplně lusknutím prstů, když máte velký projekt.

Proto mě potěšila odpověď od lidí z @czccz, kteří tvrdí že se [na HTTPS snaží přejít](https://twitter.com/tomazany/status/545165797451366401). @Mallcz už to má [skoro doladěné](https://twitter.com/whizz/status/545481329220276224) - velká pochvala! @Alzacz sice nějaké HTTPS má, ale [není výchozí a nepřesměrovává na něj](https://twitter.com/spazef0rze/status/545160262081183744). Ale totálně to zabili @KASAcz - nevím kdo jim dělá PR, ale tímhle si podřezali větev. Následoval samozřejmě shitstorm a velký výsměch.

<blockquote class="twitter-tweet" lang="en"><p><a href="https://twitter.com/spazef0rze">@spazef0rze</a> Dobrý den, nevidím důvod, proč bychom to měli dělat. Žádné citlivé údaje na webu nemáme - čísla kreditek, rodné nebo čísla OP...</p>&mdash; KASA.cz (@KASAcz) <a href="https://twitter.com/KASAcz/status/545207424010174466">December 17, 2014</a></blockquote>

V tomhle jsou právě úplně mimo. Minimálně heslo je vždy soukromý údaj a email může být taky soukromý údaj, pokud se s ním přihlašujete.

**Neexistuje relevantní důvod, proč na webu HTTPS nemít.**

Pořád si nepřipadáte dostatečně motivovaní? Tak to je na čase si přečíst článek od [Petra Soukupa - jak nakoupit na Alza.cz za cizí peníze](https://www.souki.cz/jak-jde-na-alza-cz-nakoupit-za-cizi-penize). Je to velice snadné, Alza totiž nepoužívá https správně :)


## Instalace Nginx

Kompilovat nginx samozřejmě **nemusíte**, já to dělám proto, že je to zábava a často je to rychlejší než čekat na hotové balíčky.

- stáhnout aktuální nginx na https://nginx.org/en/download.html
- ověřit podpis staženého balíku přes [PGP](https://wiki.nginx.org/Pgp)
- rozbalit
- [zkompilovat](https://nginx.org/en/docs/configure.html)

~~~ shell
$ ./configure --with-ipv6 --with-http_ssl_module --with-http_spdy_module \
    --with-http_realip_module --with-http_gunzip_module \
    --with-http_gzip_static_module --with-google_perftools_module
$ make
$ sudo make install
~~~

Nainstaluje se v systému do `/usr/local/nginx`.


### Oficiální repozitáře

Obecně je lepší si to prostě nainstalovat z repozitáře.
A oficiální repozitáře, které obsahují balíky z mainline, [jsou na této adrese](https://nginx.org/en/linux_packages.html).



## Získání HTTPS certifikátu

V současnosti mám na webech (které jsem nastavoval já) certifikáty od dvou poskytovatelů, které si představíme za moment.


### Generování žádosti o podpis

Předtím než koukneme jak získat certifikát, tak si vygenerujeme žádost o podpis certifikátu pomocí `openssl`.
Jenom upozorním, že tenhle krok je naprosto volitelný, oba poskytovatelé umějí i tuto žádost vygenerovat bezpečně přímo na webu, dělat to tedy nemusíte.
Já si rád ale generuju žádosti sám, protože je to větší geekovina :)

Jak pojmenujete soubory není důležité, ale je fajn v tom mít pořádek.
Stejně tak je dobrým zvykem generovat žádost přímo na serveru přes ssh, kde bude HTTPS použito.
Privátní klíč by server ideálně neměl vůbec opustit.

Následujícím příkazem si vygenerujeme privátní klíč a žádost o podpis certifikátu

~~~ shell
$ openssl req -new -sha256 -nodes -newkey rsa:2048 -keyout kdyby.org-decrypted.key -out kdyby.org.csr
Country Name (2 letter code) [AU]:CZ
State or Province Name (full name) [Some-State]:Jižní Morava
Locality Name (eg, city) []:Brno
Organization Name (eg, company) [Internet Widgits Pty Ltd]:Filip Procházka
Organizational Unit Name (eg, section) []:
Common Name (e.g. server FQDN or YOUR name) []:kdyby.org
Email Address []:mr@fprochazka.cz

Please enter the following 'extra' attributes to be sent with your certificate request
A challenge password []:
An optional company name []:
~~~

Tento postup použijeme u obou poskytovatelů, které si teď představíme.
Děkuji Ondřeji Caletkovi z komentářů, že tuhle část zjednodušil :)


### Startssl.com

Na svoje projekty strašně rád používám certifikáty od [startssl.com](https://www.startssl.com/), protože se mi moc líbí jejich filozofie (=za věci co může udělat stroj automaticky se neplatí) a to jak to funguje. Zaplatíte (US$ 59.90 ~ 1300 CZK) pouze a jenom za ověření vaší identity (v mém případě chtěli scan dvou osobních dokladů) a v ten moment získáváte "Class2"((intermediate)) verifikaci a můžete si generovat **neomezený** počet **wildcard certifikátů** pro kolik chcete domén. Jediná podmínka je, že je vlastníte přímo vy - například [Hrachovi](https://twitter.com/hrachcz) zamítli prodloužení certifikátu pro [signaly.cz](https://www.signaly.cz/), protože je to organizace a on ji (ani tu doménu) nevlastní.

Mám je například na [kdyby.org](https://www.ssllabs.com/ssltest/analyze.html?d=kdyby.org&latest) a není to úplně marný :)

![nginx-https-spdy-kdyby-org-ssllabs](/content/nginx-https-spdy-kdyby-org-ssllabs.png)

Dokonce [rozdávají Class 1 certifikáty (pro jednu doménu a jednu její subdoménu) úplně zadarmo](https://www.startssl.com/?app=1) - ale pouze pro nekomerční weby (kontrolují to).

Na [konklone.com](https://konklone.com/post/switch-to-https-now-for-free) je velice pěkný návod i s obrázky včetně procesu registrace.
Ale pro budoucí archivaci sem přepíšu do češtiny alespoň bodově co je potřeba udělat.

- Nejprve je potřeba zvalidovat, že doménu opravdu spravujete... "Validations Wizard" -> "Domain Name Validation"... Vyplníme doménu, pošleme si email a ověříme "vlastnictví".
- Na webu si otevřete "Certificate Wizard" -> "Web Server SSL/TLS Certificate", v dalším kroku kliknout na skip, protože [žádost už máme vygenerovanou](#toc-generovani-zadosti-o-podpis), zkopírovat a vložit obsah našeho vygenerovaného `.csr` souboru (žádosti o podpis)
- Nezapomenout přidat `*.` jako povolenou doménu, potvrdit, dokončit
- Profit

Pokud by se vám nenabídla okamžitě možnost stažení vygenerovaného a podepsaného certifikátu, tak se nelekejte, náhodně si některé ještě ověřují ručně, ale žádné další peníze za to nechtějí. Jednoduše si v "Tool Box" -> "Retrieve Certificate" po dokončení najdete svůj certifikát a stáhnete si jej.

Pro správnou funkčnost na webserveru ale potřebujete ještě dva soubory.
Kořenový certifikát startssl a "Class2"((intermediate)) certifikát, který je podepsaný tím kořenovým a startssl s ním podepisuje ten váš certifikát.

~~~ shell
$ wget https://www.startssl.com/certs/sub.class2.server.ca.pem
$ wget https://www.startssl.com/certs/ca.pem
~~~


### crt.simplia.cz

Na certifikátu od Simplia běží tento blog, protože jsem zamáslil generování certifikátu na startssl.com a vygeneroval jsem ho slabý a zrušení (abych si mohl vygenerovat nový) stojí peníze (ověřuje to člověk) a když jsem si pobrečel na Twitteru, tak mi [Souki](https://twitter.com/petrsoukup) napsal email, že mi přidělil pár certifikátů zdarma na simplia.cz - za to Petrovi velký dík :)

Shop: [crt.simplia.cz](https://crt.simplia.cz/) + story: [souki.cz/ssl-certifikaty-od-99kc](https://www.souki.cz/ssl-certifikaty-od-99kc)

Tady je taky možnost získat certifikát zadarmo, ale má platnost jenom 3 měsíce.

Zde byl proces velice podobný, až na to že to nepodepisuje přímo Simplia, ale Comodo.

- Proklikáte se formulářem
- Stejně jako u startssl si [vygenerujete u sebe na počítači žádost o podpis certifikátu](#toc-generovani-zadosti-o-podpis)
- Dokončíte proces a zaplatíte za certifikát
- Během pár minut vám přijde všechno na email

Tady taky samozřejmě nezapomenout na kořenový a intermediate certifikát, ale od Comodo vám přijdou emailem všechny rovnou, takže je nemusíte stahovat zvlášť. Bacha na to, že přijde jeden root a další dva intermediate certifikáty :)


## Příprava certifikačního bundle pro nginx

Do nginxu budeme za chvíli nastavovat 3 soubory, aby správně mohl fungovat s HTTPS. Je to strašně jednoduché, jenom slepíte pár souborů dohromady.

- `kdyby.org-decrypted.key` je náš privátní klíč, ale bez hesla - ten už máme

### Startssl bundle

- `kdyby.org.bundle.crt` je tzv. bundle, ve kterém jsou dva certifikáty zároveň - nginx je posílá klientu na ověření

~~~ shell
$ cat kdyby.org.crt sub.class2.server.ca.pem > kdyby.org.bundle.crt
~~~

- `kdyby.org.bundle+root.crt` je potřeba, abychom mohli nastavit tzv. [OCSP stapling - více viz wiki](https://en.wikipedia.org/wiki/OCSP_stapling)

~~~ shell
$ cat kdyby.org.bundle.crt ca.pem > kdyby.org.bundle+root.crt
~~~

Ještě jednou pro pořádek
- z webu jsem si stáhl `kdyby.org.crt`, `sub.class2.server.ca.pem`, `ca.pem`
- vyrábím `kdyby.org.bundle.crt` a `kdyby.org.bundle+root.crt`.


### Comodo (crt.simplia.cz) bundle

- `kdyby.org.bundle.crt` - Pozor na to, že v případě Comodo, do `kdyby.org.bundle.crt` patří ještě jeden certifikát, správně tedy

~~~ shell
$ cat kdyby.org.crt COMODORSAAddTrustCA.crt COMODORSADomainValidationSecureServerCA.crt > kdyby.org.bundle.crt
~~~

- `kdyby.org.bundle+root.crt`

~~~ shell
$ cat kdyby.org.bundle.crt AddTrustExternalCARoot.crt > kdyby.org.bundle+root.crt
~~~

Ještě jednou pro pořádek
- emailem mi přišly `kdyby.org.crt`, `COMODORSAAddTrustCA.crt`, `COMODORSADomainValidationSecureServerCA.crt`, `AddTrustExternalCARoot.crt`
- vyrábím `kdyby.org.bundle.crt` a `kdyby.org.bundle+root.crt`.



## Nastavení HTTPS + SPDY

Rozdělíme si to pěkně na části a vysvětlíme si co která část dělá :)


### nginx.conf

Jako první si vygenerujeme krásný unikátní vstup pro [eliptické křivky](https://en.wikipedia.org/wiki/Elliptic_curve_Diffie%E2%80%93Hellman). Generuje se to docela dlouho :)

~~~ shell
$ openssl dhparam -outform pem -out dhparam2048.pem 2048
~~~

Upozorňuji, že soubor by měl mít co nepřísnější oprávnění a měl by jít číst pouze rootem a měl by mít úplně odebrané write práva.

~~~ shell
$ sudo chown root:root dhparam2048.pem
$ sudo chmod 0400 dhparam2048.pem
~~~

Upozorňuji, že tohle zdaleka není kompletní konfigurační `http {}` blok, ale jsou to jenom části, které máte *přidat* navíc.
Hlavně je potřeba pohlídat cestu k `dhparam2048.pem`, aby seděla podle toho kde soubor máte ve vašem systému.

<script src="https://gist.github.com/fprochazka/04df7f71222e8056af5c.js?file=nginx.conf"></script>


### php.conf

A když už jsme u toho dekomponování, vyhodíme si bokem i společná nastavení pro PHP do `php.conf`.

<script src="https://gist.github.com/fprochazka/04df7f71222e8056af5c.js?file=php.conf"></script>


### hsts.conf

Tohle už je malinko onanie, ale když ona je ta hlavička docela dlouhá :)

<script src="https://gist.github.com/fprochazka/04df7f71222e8056af5c.js?file=hsts.conf"></script>

- Klíčové slovo `always` [je možné přidávat až od nginx 1.7.5](https://nginx.org/en/docs/http/ngx_http_headers_module.html#add_header) a díky němu se hlavička pošle nezávisle na tom jaký vracíte http kód
- pokud nechcete, nebo nemůžete, tak z hlavičky HSTS můžete vyhodit část `; includeSubDomains; preload` a dejte si opravdu velký pozor na `includeSubDomains`, protože pokud nemáte wildcard certifikát tak vám přestanou fungovat subdomény, které nemají HTTPS, protože prohlížeče je prostě budou ignorovat


### Virtualhosty

Teď potřebujeme, aby jeden ze serverů byl výchozí (aby SNI správně fungovalo) - jeho certifikáty se pošlou prohlížeči, pokud nepodporuje SNI. Já si jako vychozí zvolil `kdyby.org` - je to poznat podle klíčového slova `default` na řádku s `listen`.

Pokud potřebujete podporovat staré prohlížeče, [konrétně IEčka na Windows XP](https://cs.wikipedia.org/wiki/Server_Name_Indication#Nepodporovan.C3.A9_opera.C4.8Dn.C3.AD_syst.C3.A9my_a_prohl.C3.AD.C5.BEe.C4.8De), tak tam SNI fungovat nebude. Musíte mít tedy na jeden certifikát (doména a subdomény) jednu IPv4 adresu.
Pokud se vám tam ale povede nainstalovat Chrome nebo Firefox, tak v nich to funguje i na XP.
Myslím si ale, že je na čase se na staré XP vykašlat ;)

Dávejte si pozor, že tady nastavujeme cesty k těm třem souborům, které jsme si před chvíli připravili - tedy k dešifrovanému privátnímu klíči, certifikačnímu bundle a druhému bundle s root certifikátem. A opět bacha na oprávnění u certifikátů a těch klíčů - nejlepší je mít povolený jenom read rootem jako u `dhparam2048.pem`.

<script src="https://gist.github.com/fprochazka/04df7f71222e8056af5c.js?file=kdyby.org.conf"></script>

A teď ještě jeden nevýchozí vhost, se spoustou dalších přesměrování, protože na filip-prochazka.com nepoužívám subdomény

<script src="https://gist.github.com/fprochazka/04df7f71222e8056af5c.js?file=filip-prochazka.com.conf"></script>


### IPv6

Nejprve je potřeba nastavit `AAAA` záznam v DNS vaší domény. Můžete si otestovat, jestli se to už projevilo, pomocí [IPv6 validátoru](https://ipv6-test.com/validate.php), u mně to Wedos DNS serverům trvalo několik hodin než se to obnovilo.

Důležité je taky aby váš server naslouchal po síti na IPv6, moje nastavení souboru `/etc/network/interfaces` (mám debian) vypadá následovně.
Ten první `iface` jsem tam měl už po instalaci automaticky. Přidal jsem jenom ten blok s `inet6`.

Pokud to váš hosting podporuje, tak adresu a bránu najdete pravděpodobně v administraci hostingu.
Nastavit tohle může být ale trošku nebezpečné, protože když to uděláte špatně, může se stát že se na server už nedostanete :)

<script src="https://gist.github.com/fprochazka/04df7f71222e8056af5c.js?file=interfaces"></script>

Když tohle všechno funguje, můžeme pozapínat v nginxu aby naslouchal na IPv6 a to jednoduše tím,
že odkomentujeme připravené `listen` direktivy, které obsahují `[::]:`.
Celý trik je v tom, že jenom zduplikujete vždy listen a přidáte do něj `[::]:`.

Dávejte si bacha na to, že když budete do konfigurace nastavovat flag `ipv6only=on`, tak může být v úplně celé konfiguraci právě jednou a né vícekrát.



### Uložit a restartovat

Pustíme configtest a když dobrý tak restartneme nginx a budeme doufat :)

~~~ shell
$ sudo service nginx configtest
~~~

Tohle je +- aktuální konfigurace která v tento moment pohání tenhle web. Pokud tam najdete bezpečnostní chybu, tak mi prosím nejprve napište email, ať mám šanci si to opravit - díky! #whiteHat


## Kontrola

Známku kdyby.org už jsme viděli, co takhle [filip-prochazka.com](https://www.ssllabs.com/ssltest/analyze.html?d=filip-prochazka.com&latest)?

![nginx-https-spdy-filip-prochazka-com-ssllabs](/content/nginx-https-spdy-filip-prochazka-com-ssllabs.png)


Výborně :) A teď ještě spdycheck - [kdyby.org](https://spdycheck.org/#www.kdyby.org) a [filip-prochazka.com](https://spdycheck.org/#filip-prochazka.com). Mělo by to vypadat takto:

![nginx-https-spdy-kdyby-org-spdycheck](/content/nginx-https-spdy-kdyby-org-spdycheck.png)

A ještě [IPv6 test](https://ipv6-test.com/validate.php)

![nginx-https-spdy-filip-prochazka-com-ipv6-test](/content/nginx-https-spdy-filip-prochazka-com-ipv6-test.png)

Na žádné chyby byste narazit neměli, pokud ano, tak maximálně nějaké drobnosti typu špatná cesta.
Rozhodně nejsem žádnej nginx master - celý tohle jsem slepil z X jiných návodů, ale funguje to docela dobře :)
Co tím chci říct je, že pokud narazíte na nějaký problém s touhle konfigurací, tak nevím jestli budu schopný poradit, ale zatím na všechno jsem vygooglil vždy článek, takže věřím že si taky poradíte. A kdybyste opravdu nevěděli, můžete se zkusit zeptat mě.

Pokud máte nápad na jakékoliv vylepšení (pokud to nebude fix bezpečnostní chyby - v tom případě mi napište email prosím) tak do komentářů s tím - díky!


## Zdroje:

- [Configuring nginx for SSL SNI vhosts by @StefanWallin](https://gist.github.com/StefanWallin/5690c76aee1f783c3d57)
- [Security Labs: RC4 in TLS is Broken: Now What? - Qualys Community](https://community.qualys.com/blogs/securitylabs/2013/03/19/rc4-in-tls-is-broken-now-what)
- [Security/Server Side TLS - Mozilla wiki](https://wiki.mozilla.org/Security/Server_Side_TLS#Intermediate_compatibility_.28default.29)
- [Nginx HTTPS / SSL Google SPDY configuration](https://centminmod.com/nginx_configure_https_ssl_spdy.html)
- [How to enable SPDY with nginx (Debian Squeeze) - cowthink.org](https://cowthink.org/how-to-enable-spdy-with-nginx-debian-squeeze/)
- [Security Labs: SHA1 Deprecation: What You Need ... - Qualys Community](https://community.qualys.com/blogs/securitylabs/2014/09/09/sha1-deprecation-what-you-need-to-know)
- [Forward secrecy - Wikipedia](https://en.wikipedia.org/wiki/Forward_secrecy)
- [NGINX SSL Termination - nginx.com](https://nginx.com/resources/admin-guide/nginx-ssl-termination/)
- [TLS has exactly one performance problem: it is not used widely enough](https://istlsfastyet.com/)
- [Jak rozjet IPv6 na vlastním serveru? - jklir.net](https://blog.jklir.net/jak-rozjet-ipv6-na-vlastnim-serveru-20120919.html)
- [How To Set Up SSL Vhosts Under Nginx + SNI Support + IPv6](https://www.howtoforge.com/how-to-set-up-ssl-vhosts-under-nginx-plus-sni-support-ubuntu-11.04-debian-squeeze-p2)
- [NetworkConfiguration - Debian Wiki](https://wiki.debian.org/NetworkConfiguration)

A taky bych chtěl poděkovat [Jardovi Hanslíkovi](https://twitter.com/kukulich), [Petrovi Soukupovi](https://twitter.com/petrsoukup), Tadeáši Menglerovi a [Michalu Špačkovi](https://twitter.com/spazef0rze), se kterými jsem si vyměnil v emailech jednou tolik textu co je v tomhle článku :)
