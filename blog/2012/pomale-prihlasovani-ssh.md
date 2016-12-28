Date: 2012-02-20
Tags: Linux, Domácí Server

# Pomalé přihlašování na SSH?


Mohlo by pomoct, přidat následující řádek do `/etc/ssh/sshd_config`

``` ini
UseDNS no
```


Zdroj: http://www.netadmintools.com/art605.html
