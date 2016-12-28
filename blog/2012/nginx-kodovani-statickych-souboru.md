Date: 2012-09-16 17:00
Tags: NGINX, Domácí Server, Linux

# NGINX: kódování statických souborů


Pokud máte na serveru "nějaké statické soubory":/humans.txt, může se stát, že prohlížeč vám z nich udělá rozsypaný čaj, protože mu neposíláte kódování a [on si tipne špatně](https://twitter.com/petrsoukup/status/247321564549373953).

Nejsnadnější řešení s NGINXem je vnutit mu výchozí kódování

```config
http {
	...
	charset utf-8;
	...
}
```

Teď už to funguje správně

```shell
$ curl -v http://filip-prochazka.com/humans.txt
...
< Content-Type: text/plain; charset=utf-8
```
