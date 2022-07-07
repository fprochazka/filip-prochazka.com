---
layout: blogpost
title: "Nginx a SSL přesměrování pro všechny subdomény"
permalink: blog/nginx-a-ssl-presmerovani-pro-vsechny-subdomeny
date: 2014-07-04 18:00
tag: ["NGINX"]
---

[Jak nastavit SSL pro nginx](https://nginx.org/en/docs/http/configuring_https_servers.html) je krásně popsané v dokumentaci.
Co mě ale zarazilo, tak že jsem nenašel jak přesměrovat všechny subdomény a jejich requesty na sebe sama ale pod HTTPS.
Respektive, našel jsem [jedno relevantní vlánko na serverfault](https://serverfault.com/a/250488), ale to nefunguje jak potřebuju.


Když použiju

~~~ nginx
server_name     kdyby.org *.kdyby.org;
return          301 https://$servername$request_uri;
~~~

tak to se mi to nedařilo přinutit přesměrovávat [help.kdyby.org](https://help.kdyby.org) na [https://help.kdyby.org](https://help.kdyby.org),
místo toho to skákalo na [https://kdyby.org](https://kdyby.org) a házelo chyby.
Proměnná `$server_name` totiž obsahuje první doménu, co jí nastavíte (v tomhle případě `kdyby.org`).

Jediné co mi fungovalo, tak použít regulární výrazy a přidat si vlastní proměnnou.

~~~ nginx
server {
        listen          80;
        server_name     ~^(?<servername>(:?.+\.)?kdyby.org)$;
        return          301 https://$servername$request_uri;
}

server {
        listen          443 default ssl;

        # ...
}
~~~

Doufám, že na to existuje lepší řešení (jestli víte, podělte se prosím v komentářích), ale tohle je dostatečně funkční, takže za mě fuck it :)


**UPDATE:** když jsem projížděl dokumentaci poprvé, tak jsem přehlédl proměnnou [$host](https://nginx.org/en/docs/http/ngx_http_core_module.html#var_host),
na kterou mě teď upozornil [@tomasfejfar](https://twitter.com/tomasfejfar/status/485144989937442816), díky!

Řešení je tedy následující

~~~ nginx
server {
        listen          80;
        server_name     kdyby.org *.kdyby.org;
        return          301 https://$host$request_uri;
}

server {
        listen          443 default ssl;

        # ...
}
~~~
