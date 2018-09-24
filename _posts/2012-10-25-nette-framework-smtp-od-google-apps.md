---
layout: blogpost
title: "Nette Framework: SMTP od Google Apps"
permalink: blog/nette-framework-smtp-od-google-apps
date: 2012-10-25 12:00
tag: ["Nette Framework", "Google"]
---

Skoro každá aplikace potřebuje odesílat emaily. Určitě se vám už stalo, že emaily nedorazily tak, jak by měly. Emaily odeslané pomocé funkce `mail()` v PHP totiž dost často neprojdou agresivnými spamovými filtry.

Pokud nemáte zdroje nastavovat si kvalitně "SMTP server"((to je to, co ty emaily reálně odesílá)), nebo ten co používáte není úplně vyhovující, stojí za zvážení Google Apps. Je možné mít k jedné doméně až 10 schránek zdarma. Nám bude stačit pro začátek jedna.

Nejprve je nutné [si vytvořit účet pro doménu](https://www.google.com/a/cpanel/standard/new?hl=cs), kde máme web a ze které budeme odesílat emaily. Po vyplnění formuláře budete muset potvrdit vlastnictví domény.

Dalším krokem bývá nastavení MX záznamů domény, abychom emaily mohli také přijímat.

![google-apps-mx](/content/google-apps-mx.png)

<small>(aktuální MX záznamy jdou zkopírovat z průvodce v Google Apps)</small>


## Použití v Nette

Následujících pár řadků nám nastaví [SMTP mailer](https://api.nette.org/2.0/Nette.Mail.SmtpMailer.html)

~~~ neon
production:
    nette:
        mailer:
            smtp: true
            host: smtp.gmail.com
            secure: ssl
            username: no-reply@kdyby.org
            password: ****
~~~

a můžeme začít emaily odesílat

~~~ php
/**
 * @var Nette\Mail\IMailer
 */
private $mailer;

public function injectMailer(Nette\Mail\IMailer $mailer)
{
    $this->mailer = $mailer;
}

public function registrationFormSubmitted($form)
{
    // ...

    $message = new \Nette\Mail\Message();
    $message->setSubject('Registrace')
        ->setFrom('bot@kdyby.org')
        ->addTo($registrationEmail)
        ->setHtmlBody($registrationEmailTemplate);

    $this->mailer->send($message);
}
~~~
