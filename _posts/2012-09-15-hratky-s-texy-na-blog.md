---
layout: blogpost
title: "Hrátky s Texy! na blog"
permalink: blog/hratky-s-texy-na-blog
date: 2012-09-15
tag: ["PHP", "Texy!", "FSHL"]
---

[Davídek](https://davidgrudl.com) nám ukázal, jak má nastavené texy na [https://nette.org](https://nette.org), takže jsem toho využil a napsal si nad Texy! vrstvičku.

Stará se o zvýrazňování kódu a taky zpracovává magické "meta makra".
Potřeboval jsem také, aby do zvárazněného kódu generoval seznamy, tak jako to ve své dokumentaci dělá Twitter Bootstrap, takže jsem povolil `ol` v elementu `pre`.

Další požadavek, na kterém jsem se docela zapotil, bylo odstraňování hlavního nadpisu z výsledného kódu a kontrola, jestli obsahuje odkaz.
Chci si totiž nadpis renderovat nad článkem zvlášť sám, kvůli tomu, aby se stejný HTML kód dal použít v RSS a nebyl v něm 2x nadpis.
Implementace je dost naivní, ale dělám to pro sebe, tak si budu muset pamatovat, že to funguje pouze pokud odkaz obaluje celý obsah nadpisu.

Tohle fungovat bude

~~~ texy
"**nadpis**":http://example.com
***
~~~

ale tohle už fungovat nebude

~~~ texy
**"nadpis":http://example.com**
***
~~~

Texy! je nastaveno s vědomím, že výsledek bude na mém blogu - dovolí mi skoro vše.

~~~ php
use Nette\Utils\Html;
use Nette\Utils\Strings;

class Processor extends Nette\Object
{
    /** @var \Texy */
    private $lastTexy;

    /** @var \FSHL\Highlighter */
    private $highlighter;

    /** @var array */
    private $meta = array();

    /** @var string */
    private $title;

    /** @var array */
    private static $highlights = array(
        'block/code' => TRUE,
        'block/php' => 'FSHL\Lexer\Php',
        'block/neon' => 'FSHL\Lexer\Neon',
        'block/config' => TRUE, // @todo
        'block/sh' => TRUE, // @todo
        'block/texy' => TRUE, // @todo
        'block/javascript' => 'FSHL\Lexer\Javascript',
        'block/js' => 'FSHL\Lexer\Javascript',
        'block/css' => 'FSHL\Lexer\Css',
        'block/sql' => 'FSHL\Lexer\Sql',
        'block/html' => 'FSHL\Lexer\Html',
        'block/htmlcb' => 'FSHL\Lexer\Html',
    );

    public function __construct(\FSHL\Highlighter $highlighter)
    {
        $this->highlighter = $highlighter;
    }

    public function process($text)
    {
        $this->meta = array();
        $this->title = array('link' => NULL, 'heading' => NULL, 'el' => NULL);
        return $this->createTexy()->process($text);
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getLastTexy()
    {
        return $this->lastTexy;
    }

    protected function createTexy()
    {
        $texy = new \Texy();

        // obecné nastavení
        $texy->allowedTags = \Texy::ALL;
        $texy->linkModule->root = '';
        $texy->tabWidth = 4;
        $texy->phraseModule->tags['phrase/strong'] = 'b';
        $texy->phraseModule->tags['phrase/em'] = 'i';
        $texy->phraseModule->tags['phrase/em-alt'] = 'i';

        // nadpisy
        $texy->headingModule->top = 1;
        $texy->headingModule->generateID = TRUE;
        $texy->addHandler('afterParse', array($this, 'headingHandler'));

        // čísla řádků pro twitter bootstrap
        $texy->dtd['pre'][1]['ol'] = 1;

        // vypne generování bílých znaků ve výsledném kódu,
        // aby se neroztahoval kód v elementu <pre>
        $texy->htmlOutputModule->indent = FALSE;

        // <code>
        $texy->addHandler('block', array($this, 'blockHandler'));

        // meta
        $texy->registerBlockPattern(
            array($this, 'metaHandler'),
            '#\{\{([^:]+):([^:]+)\}\}$#m', // block patterns must be multiline and line-anchored
            'metaBlockSyntax'
        );

        // return
        return $this->lastTexy = $texy;
    }

    /**
     * Metoda vykuchá element hlavního nadpisu z výsledného HTML
     * a taky koukne, jeslti nadpis neobsahuje odkaz.
     * @internal
     */
    public function headingHandler(\Texy $texy, \TexyHtml $DOM, $isSingleLine)
    {
        list($title) = $texy->headingModule->TOC;

        // zkopírovat element
        $titleEl = Html::el($title['el']->getName(), $title['el']->attrs);
        foreach ($title['el']->getChildren() as $child) {
            $titleEl[] = $child;
        }

        // uklidit
        $title['el']->attrs = array();
        $title['el']->removeChildren();
        $title['el']->setName(NULL);

        // parsování odkazu
        foreach ($titleEl->getChildren() as $i => $child) {
            $matches = Strings::matchAll(
                $texy->unProtect($child), // texy magie
                '~<([\\w]+)([^>]*?)(([\\s]*\/>)|(>((([^<]*?|<\!\-\-.*?\-\->)|(?R))*)<\/\\1[\s]*>))~sm',
                PREG_OFFSET_CAPTURE
            );
            if (!$matches) break;
            list($tag) = $matches;

            $titleEl[$i] = $el = Html::el($tag[1][0] . ' ' . $tag[2][0]);
            $el->setHtml($tag[6][0]);

            if ($el->getName() === 'a') {
                $this->title['link'] = $el->attrs['href'];
            }
        }

        // obsah nadpisu
        $this->title['heading'] = $titleEl->getText();
        $this->title['el'] = $titleEl;
    }

    /**
     * Parsuje meta značky {{key: value}}
     * @internal
     */
    public function metaHandler(\TexyParser $parser, array $matches, $name)
    {
        list(, $metaName, $metaValue) = $matches;
        $this->meta[] = array(
            trim(Strings::normalize($metaName)),
            trim(Strings::normalize($metaValue))
        );
    }

    /**
     * Zýrazňuje kód
     * @internal
     */
    public function blockHandler(\TexyHandlerInvocation $invocation, $blockType, $content, $lang, $modifier)
    {
        if (isset(self::$highlights[$blockType])) {
            list(, $lang) = explode('/', $blockType);
        } else {
            return $invocation->proceed($blockType, $content, $lang, $modifier);
        }

        $texy = $invocation->getTexy();
        $content = \Texy::outdent($content);

        // zvýraznění syntaxe
        if (class_exists($lexerClass = self::$highlights[$blockType])) {
            $content = $this->highlighter->highlight($content, new $lexerClass());
        } else {
            $content = htmlspecialchars($content);
        }

        $elPre = \TexyHtml::el('pre');
        if ($modifier) $modifier->decorate($texy, $elPre);
        $elPre->attrs['class'] = 'src-' . strtolower($lang) . ' prettyprint linenums';

        // čísla řádků
        $elOl = $elPre->create('ol', array('class' => 'linenums'));
        foreach (Strings::split($content, '~[\n\r]~') as $i => $line) {
            $elLi = $elOl->create('li', array('class' => 'L' . $i));
            $elLi->create('span', $texy->protect($line, \Texy::CONTENT_BLOCK));
        }

        return $elPre;
    }

}
~~~

Kvůli tomu, že každý řádek nyní obaluji prvkem `<li>`, je potřeba upravit FSHL, aby se nám nekřížily tagy přes řádek.

~~~ php
class FshlHtmlOutput implements \FSHL\Output
{
    private $lastClass = null;

    public function template($part, $class)
    {
        $output = '';
        if ($this->lastClass !== $class) {
            if (null !== $this->lastClass) $output .= '</span>';
            if (null !== $class) $output .= '<span class="' . $class . '">';
            $this->lastClass = $class;
        }
        $part = htmlspecialchars($part, ENT_COMPAT, 'UTF-8');
        if ($this->lastClass && strpos($part, "\n") !== FALSE) {
            $endline = "</span>\n" . '<span class="' . $this->lastClass . '">';
            $part = str_replace("\n", $endline, $part);
        }
        return $output . $part;
    }

    public function keyword($part, $class)
    {
        $output = '';
        if ($this->lastClass !== $class) {
            if (null !== $this->lastClass) $output .= '</span>';
            if (null !== $class) $output .= '<span class="' . $class . '">';
            $this->lastClass = $class;
        }
        return $output . htmlspecialchars($part, ENT_COMPAT, 'UTF-8');
    }
}
~~~


Výsledný `Processor` pak používám následovně

~~~ php
$processor = new Processor(new FSHL\Highlighter(new FshlHtmlOutput()));
$html = $processor->process($texy);
$meta = $processor->meta;
~~~


Byl jsem krapet v šoku, když jsem zjistil, že tento krásný blok s kódem není ve standardní distribuci Twitter Bootstrap. Kdo je líný kuchat to z jejich webu, tak CSS je zde:

~~~ css
.prettyprint {
    padding: 8px; background-color: #f7f7f9; border: 1px solid #e1e1e8;
}
.prettyprint.linenums {
    -webkit-box-shadow: inset 45px 0 0 #fbfbfc, inset 46px 0 0 #ececf0;
    -moz-box-shadow: inset 45px 0 0 #fbfbfc, inset 46px 0 0 #ececf0;
    box-shadow: inset 45px 0 0 #fbfbfc, inset 46px 0 0 #ececf0;
}
ol.linenums {
    margin: 0 0 0 43px; /* IE indents via margin-left */
}
ol.linenums li {
    padding-left: 6px; color: #bebec5; line-height: 20px; text-shadow: 0 1px 0 #fff;
}
ol.linenums li > span {
    color: black;
}
~~~
