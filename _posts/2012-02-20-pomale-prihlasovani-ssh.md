---
layout: blogpost
title: "Pomalé přihlašování na SSH?"
permalink: blog/pomale-prihlasovani-ssh
date: 2012-02-20
tag: ["Linux", "Domácí Server"]
---

Mohlo by pomoct, přidat následující řádek do `/etc/ssh/sshd_config`

~~~ ini
UseDNS no
~~~


Zdroj: http://www.netadmintools.com/art605.html
