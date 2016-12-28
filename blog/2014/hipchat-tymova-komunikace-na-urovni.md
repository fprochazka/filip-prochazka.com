Date: 2014-06-17 18:15
Tags: HipChat, Travis, Github, NewRelic

# HipChat - týmová komunikace na úrovni

V [DámeJídlo.cz](https://www.damejidlo.cz/vitejte) děláme tak trochu distribuovaně. A můžu za to hlavně já, protože se mi nechtělo stěhovat do Prahy. Takže už rok a půl komunikujeme přes Github, občas přes emaily a hlavně přes chat.

První co se zavedlo s mým příchodem do firmy, byl skype chat pro všechny devs. Což na lidskou komunikaci funguje výborně, píšete hlavně do společného, aby všichni měli přehled na čem se dělá a občas si něco pořešíte privátně.

Ovšem na komunikaci programátorů to funguje otřesně. Proč? Když něco vyvíjíte, tak chcete mít co největší přehled, o co nejvíce věcech a ideálně na jednom místě. Do skype se dost složite píšou roboti, co vám pošlou do společného zprávu vždy, když někdo pouští deploy nebo spadne build.

Takže jsme se přes rok rozhoupávali, že přejdeme na vlastní jabber chat. A protože probíráme supertajné věci, měl to být samozřejmě náš vlastní server, ideálně na vlastní VPS. Potom že si tam nahodíme robota a budeme žít šťastně až do smrti. To samozřejmě ztroskotalo na tom, že se to buďto opakovaně odkládalo, nebo se to nikomu nechtělo nastavovat.

Zkoušeli jsme i [gitter](https://gitter.im/), který se našim představám blížil úplně nejvíc, ale vydrželi jsme to dva dny a bugy a nedostatky převládly a vrátili jsme se ke skype.

A pak jsme objevili [HipChat](https://www.hipchat.com/) a v ten moment byla zabita poslední naděje na jabber server, protože tomuhle se těžko konkuruje.


## HipChat

Je cloud-based služba, kterou provozuje Atlassian a má hromadou **hotových** integrací.
To, že má klienty pro všechny platformy na telefonech (iOS i Android) i počítačích (Mac, Win i Linux), už je jen třešnička.
A ještě navíc je strašně levnej.

V dalších pár odstavích si ukážeme, jak ho nastavit a příklady několika integrací, které používáme.


## Notifikace: Travis

Do `.travis.yml` stačí přidat pár řádků

```neon
notifications:
  hipchat:
    rooms:
      - secret-api-key@room-number
    template:
      - '<a href="%{build_url}">build#%{build_number}</a> on %{branch} by %{author}: %{message}<br> - %{commit_message} (<a href="%{compare_url}">%{commit}</a>)'
    format: html
  email: false
```

A už to lítá

> ![hipchat-travis](/content/hipchat-travis.png)
> <small>Travis notifikace v chatu</small>


## Notifikace: Vlastní deploy

Hipchat má našlapaný api a můžete si tam poslat co chcete. My máme deploy script v bashi, takže notifikace posíláme takto

```php
#!/bin/bash
function hipchat_notify() {
        export CHAT_NOTIFY="$1"

        CHAT_COlOR="$2"
        if [ -z "$CHAT_COlOR" ]; then
                CHAT_COlOR="yellow"
        fi

        curl -X POST \
                -d "message=$(php -r 'echo rawurlencode(getenv("CHAT_NOTIFY"));')" \
                "https://api.hipchat.com/v1/rooms/message?room_id=room-number&from=Deploy&message_format=html&notify=1&color=$CHAT_COLOR&format=json&auth_token=secret-api-key" \
                >/dev/null 2>/dev/null || echo "HipChat notification failed"
}

# ...

hipchat_notify "$GIT_MERGE_AUTHOR just deployed <a href=\"https://github.com/damejidlo/damejidlo/commit/$REVISION\">$REVISION</a> to <a href=\"https://$DOMAIN\">$(hostname)</a>, downtime was $DOWNTIME_SEC seconds"
```

> ![hipchat-deploy](/content/hipchat-deploy.png)
> <small>Deploy notifikace v chatu</small>


## Notifikace: Github

Na githubu je na HipChat hotová integrace, takže stačí nastavit token a místnost. A doporučuji vypnout komentáře (dělá to strašnej spam)

> ![hipchat-github](/content/hipchat-github.png)
> <small>Nastavení github hooku</small>

Každý commit do masteru se ukáže v chatu, takže hovada co nedělají pullreqesty se hned prozradí

> ![hipchat-github-commit](/content/hipchat-github-commit.png)
> <small>Github notifikace v chatu</small>

Osobně mám vyplé emaily z githubu, takže mi moc vyhovuje, že všechno vidím v chatu a nemusím chodit kontrolovat notifikace. Uvidíte taky všechny otevření/zavření issue a otevření/merge/close pullrequestů, editaci wiki atd.


## Notifikace: NewRelic

Nejprve si založíme "notifikační kanál"

> ![hipchat-newrelic-create-channel](/content/hipchat-newrelic-create-channel.png)

Potom ho přiřadíme aplikaci

> ![hipchat-newrelic-channel](/content/hipchat-newrelic-channel.png)

A pak už si jen užíváte spam

> ![hipchat-newrelic](/content/hipchat-newrelic.png)
> <small>Newrelic notifikace v chatu</small>


## Blbinky

Podle mě naprostá killer feature je taky inlinování. Umí issues a commity z Githubu (pokud jsou public), Tweety, Youtube videa, a hlavně gify

> ![hipchat-gif](/content/hipchat-gif.png)
> <small>Screenshot gifu se bohužel nehýbe :(</small>


Pokud máte také vývojový tým přes více měst/států, jak komunikujete vy?
