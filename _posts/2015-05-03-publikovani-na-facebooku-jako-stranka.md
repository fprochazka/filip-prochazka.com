---
layout: blogpost
title: "Publikování na facebooku jako stránka"
permalink: blog/publikovani-na-facebooku-jako-stranka
date: 2015-05-03 15:30
tag: ["Facebook", "Kdyby", "PHP"]
---

Včera se za mnou stavil kamarád s úplatky, že by potřeboval pomoct rozběhat automatické přispívání na Facebook Page. Protože Facebook zase nedávno udělal novou verzi api, tentokrát [v2.3](https://developers.facebook.com/docs/apps/upgrading/#v22tov23), tak spousta návodů jak publikovat na stránky jménem stránky je trošku obsolete a hlavně, ještě nikdo nenapsal návod pro [Kdyby/Facebook](https://github.com/Kdyby/Facebook/blob/master/docs/en/index.md) :) Tak jsem na to s ním sedl, opravil pár compatibility drobností v Kdyby/Facebook a během půl hodinky nám to fungovalo.

<!--more-->
## Založení stránky a aplikace

Nejprve si [založíme novou aplikaci](https://developers.facebook.com/apps/), případně pokud máte existující aplikaci, doporučuji pro práci na localhostu si založit testovací aplikaci.

![publikovani-na-fb-create-test-app](/content/publikovani-na-fb-create-test-app.png)

Pro funkční přihlašování samozřejmě budeme potřebovat App Id a App Secret.

A protože chceme na stránku publikovat, budeme potřebovat, aby nám Facebook schválil práva `publish_pages` a `manage_pages`. To se dělá v nastavení aplikace záložkou "Status & Review", kde klikneme na "Start a Submission", najdeme práva které potřebujeme, potvrdíme a pak musíme ještě vyplnit proč ta práva chceme. Facebook je posledních pár verzí API opravdu hodně přísnej na to co komu povolí, takže opravdu musíte napsat dobré důvody proč ty práva chcete. Dokud vám ty práva neschválí, bude všude hromada warningů u přihlašovacích dialogů, ale mělo by fungovat proti tomu vyvíjet a testovat.

Dále je potřeba [založit novou stránku](https://www.facebook.com/pages/create/) případě použít existující. Na záložce "Informace" stránky, na kterou chceme publikovat, ja úplně dole "ID stránky Facebooku", tohle ID budeme za moment potřebovat.


## Založení projektu a přihlašování

Nainstalujeme si vyčištěný Nette sandbox a Kdyby/Facebook

~~~ shell
$ composer create-project nette/web-project facebook-pages/
$ cd facebook-pages/
$ composer require kdyby/facebook
~~~

Když si teď v prohlížeči otevřeme aplikaci, měla by se ukázat "Congratulations!" stránka.

Abychom mohli poslat něco na Facebook Page, musíme nejprve autorizovat do naší Facebook App uživatele, který na stránku může publikovat.

Konfigurace Kdyby/Facebook [je dostatečně popsaná v dokumentaci](https://github.com/Kdyby/Facebook/blob/master/docs/en/index.md#installation), stejně tak jak rozběhat přihlášení je [popsané v dokumentaci](https://github.com/Kdyby/Facebook/blob/master/docs/en/index.md#authentication).

Nezapomenout nastavit verzi graph api.

~~~ neon
facebook:
    appId: "123"
    appSecret: "abc"
    graphVersion: v2.3
~~~

Všimněte si, že v configu nenastavuju práva na práci se stránkou, ale nechávám zde pouze výchozí práva, která mi dovolí číst informace o uživateli. Je to proto, že "dokonalé" workflow má vypadat tak, že uživatele nejprve přihlásím a požádám o read práva a až když je přihlášený a chtěl by například v administraci připojit stránky pro publikování, tak ho pošlu na Facebook login znovu, tentokrát ale s upraveným "scope" a budu po něm chtít ať mi přidá další práva. Tenhle proces se jmenuje [rerequest](https://developers.facebook.com/docs/facebook-login/login-flow-for-web/v2.3#re-asking-declined-permissions). Vyladit tohle workflow ale není cílem návodu, takže si ho tím komplikovat nebudeme a rovnou při prvním přihlášení budeme chtít všechna práva. Je ale důležité tenhle princip znát, protože Facebook by si mohl usmyslet, že to děláte špatně a zablokovat vás.

A takhle by mohlo vypadat hodně vyčištěné přihlašování bez persistence pouze se sessions

~~~ php
class HomepagePresenter extends Nette\Application\UI\Presenter
{
    /** @var \Kdyby\Facebook\Facebook @inject */
    public $facebook;

    /** @return \Kdyby\Facebook\Dialog\LoginDialog */
    protected function createComponentFbLogin()
    {
        $dialog = $this->facebook->createDialog('login');
        /** @var \Kdyby\Facebook\Dialog\LoginDialog $dialog */

        $dialog->setScope(['publish_pages', 'manage_pages']);

        $dialog->onResponse[] = function (\Kdyby\Facebook\Dialog\LoginDialog $dialog) {
            $fb = $dialog->getFacebook();

            if (!$fb->getUser()) {
                $this->flashMessage("Sorry bro, facebook authentication failed.");
                return;
            }

            try {
                $me = $fb->api('/me');
                $this->user->login(new Identity($me->id, [], (array) $me));

            } catch (\Kdyby\Facebook\FacebookApiException $e) {
                \Tracy\Debugger::log($e, 'facebook');
                $this->flashMessage("Sorry bro, facebook authentication failed hard.");
            }

            $this->redirect('this');
        };

        return $dialog;
    }

}
~~~

Do šablony `app/presenters/templates/Homepage/default.latte` si pro test vložíme něco jako

~~~ html
<div id="content">
    {if !$user->loggedIn}
        <a n:href="fbLogin-open!">Login using facebook</a>
    {else}
        {? dump($user->identity)}
    {/if}
</div>
~~~

> ![publikovani-na-fb-congratulations](/content/publikovani-na-fb-congratulations.png)

Tak a teď se kliknutím na odkaz přihlásíme, povolíme čtení profilu a v dalším kroku by po nás Facebook měl chtít potvrzení práv pro publikaci

> ![publikovani-na-fb-login-step1](/content/publikovani-na-fb-login-step1.png)

Detailněji si to můžeme ověřit rozklinutím "Vybrat co povolíte"

> ![publikovani-na-fb-login-step2](/content/publikovani-na-fb-login-step2.png)

A po potvrzení bychom měli dostat něco takového

> ![publikovani-na-fb-after-login](/content/publikovani-na-fb-after-login.png)


## Publikování na stránku

Když jsme teď přihlášeni do aplikace na našem webu, zbývají už jen dva kroky.

- získat `access_token` stránky na kterou budeme pulikovat (což je úplně jiný `access_token`, než jaký máme pro přihlášení uživatele)
- poslat příspěvek na zeď

Na endpointu `/me/accounts` máme seznam všech stránek, se kterými přihlášený uživatel může pracovat, takže si ho zavoláme

~~~ php
$accounts = $this->facebook->api('/me/accounts');
~~~

A ani ho nemusíme dumpovat, panel nám ukáže co vrací

![publikovani-na-fb-me-accounts](/content/publikovani-na-fb-me-accounts.png)

V reálné aplikaci by bylo vhodné proiterovat `$accounts->data` a uložit si je třeba do databáze, protože v jedné z položek je ten `access_token`, který nás zajímá.

Publikovat budeme na endpoint `/{page-id}/feed`, jehož [dokumentace a všechny parametry co přijímá je rozepsaná zde](https://developers.facebook.com/docs/graph-api/reference/v2.3/page/feed#publish). Pro test si do `HomepagePresenter` přidáme následující signál

~~~ php
public function handlePublishPost()
{
    $accounts = $this->facebook->api('/me/accounts');
    foreach ($accounts->data as $page) {
        if ($page->id == "425160755061") {
            $this->facebook->api('/' . $page->id . '/feed', 'POST', [
                'link' => 'https://www.kdyby.org/',
                'message' => 'testing publishing on page',
                'caption' => 'testing caption',
                'description' => 'testing description',
                'access_token' => $page->access_token,
            ]);
        }
    }
}
~~~

A do `{else}` větve v šabloně přidáme

~~~ html
<p><a n:href="publishPost!">Publikova na stránku</a></p>
~~~

Když teď klikneme na tento odkaz, měl by se na naši stránku přidat nový příspěvek

![publikovani-na-fb-published](/content/publikovani-na-fb-published.png)

V tenhle moment by měla už být automatizace publikování příspěvků hračka. Já bych si buď udělal cron pomocí [Kdyby/Console](https://github.com/Kdyby/Console/blob/master/docs/en/index.md) a nebo lépe nějaký [RabbitMQ worker](https://filip-prochazka.com/blog/kdyby-rabbitmq-aneb-asynchronni-kdyby-events) :)


## TL;DR

Fungující aplikaci [najdete na Githubu](https://github.com/fprochazka/facebook-publish-on-page), stačí jenom doplnit `appId`, `appSecret` a `fbPageId` v `config.local.neon`.
