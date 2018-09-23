---
layout: blogpost
title: "Nette Framework: kanonizace utm parametrů"
permalink: blog/nette-framework-kanonizace-utm-parametru
date: 2012-11-20 15:50
tag: ["Nette Framework", "SEO"]
---

---

**POZOR:** [IE9 a Safari 5+ zahazují fragment při opakovaném redirectu](http://stackoverflow.com/a/5915350). Takže bohužel tato technika není 100% spolehlivá.

----

Na [diskuse.jakpsatweb.cz](http://diskuse.jakpsatweb.cz/) se objevil [zajímavý dotaz](http://diskuse.jakpsatweb.cz/?action=vthread&forum=31&topic=143893#1) - jak přesměrovat request s `utm_` parametry na request bez nich a zároveň je přesunout [do fragmentu](http://api.nette.org/2.0/Nette.Http.Url.html?)

Důvod je jednoduchý, pokud vám budou na web směřovat odkazy s `utm_` parametry i bez nich, vyhledávače by si mohly myslet, že máte na webu duplicitní obsah, protože je přístupný přes více různých adres. Takový vyhledávač by se mohl nezaujatému pozorovateli jevit velice stupidní, protože "každý přece ví", že `utm_` parametry jsou jen na analýzu návštěvnosti. Ale jistota je jistota.

Prý to jde udělat jednoduchým regulárem v `.htaccess`, ale co my, co z `mod_rewrite` máme opruzeniny? My si napíšeme helper

~~~ php
class HttpHelpers extends Nette\Object
{
    public static function utmCanonicalize(Nette\Http\Request $httpRequest, Nette\Http\Response $httpResponse)
    {
        if ($httpRequest->isAjax() || (!$httpRequest->isMethod('GET') && !$httpRequest->isMethod('HEAD'))) {
            return;
        }

        $utm = array();
        foreach ($params = $httpRequest->getQuery() as $name => $value) {
            if (substr($name, 0, 4) === 'utm_') {
                unset($params[$name]);
                $utm[$name] = $value;
            }
        }

        if ($utm) {
            $url = clone $httpRequest->getUrl();
            $url->setQuery($params);
            $url->setFragment(http_build_query($utm));
            $httpResponse->redirect($url, Nette\Http\IResponse::S301_MOVED_PERMANENTLY);
            exit(0);
        }
    }
}
~~~

Asi úplně nejčistější místo pro takový kód v aplikaci by bylo `BasePresenter::canonicalize()`, ale to už máme za sebou injectování závislostí i akci - zbytečná režie kvůli takové "hlouposti". Chtělo by to trochu dříve. Přidáme ho tedy do `app/bootstrap.php`

~~~ php
// ...
$container = $configurator->createContainer();
HttpHelpers::utmCanonicalize($container->httpRequest, $container->httpResponse);
// ...
~~~

Nyní si zkuste otevřít tuto adresu

"`/blog/nette-framework-kanonizace-utm-parametru?utm_source=self&utm_medium=experiment`":/blog/nette-framework-kanonizace-utm-parametru?utm_source=self&utm_medium=experiment

A uvidíte kód v akci :)
