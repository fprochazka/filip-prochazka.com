---
layout: blogpost
title: "Logování výjimek v Javascriptu"
permalink: blog/logovani-vyjimek-v-javascriptu
date: 2012-12-1 22:20
tag: ["Javascript"]
---

Dneska ráno jsem dostal nápad, že by bylo super, kdyby se mi výjimky Javascriptu v browseru logovaly ajaxem na serveru.

Samozřejmě jako první jsem vyzkoušel [window.onerror](https://developer.mozilla.org/en-US/docs/DOM/window.onerror). Věděli jste, že tehle event je téměř zbytečný? Volá se totiž ve scope, ze kterého už pak nejde získat stack trace výjimky. Všechno co v něm dostanu je zpráva chyby, url souboru kde k ní došlo a řádek.

Tohle mi ale sakra nestačí. Viděl jsem se totiž s vymazlenou laděnkou, kde budu mít stack trace, argumenty a kontext na všech úrovních. Chrome to umí, musí to přece nějak jít! Jak jinak ale získat vyhozenou výjimku i s kompletním ocáskem?


## Preprocessor v PHP

Co takhle kdybych si obalil všechny funkce do `try {} catch {}`? To by řešilo i vyhození výjimek v eventech.

~~~ php
class JsDebugger extends Nette\Object
{
    const TOK_STRING = 'string';
    const TOK_SYMBOL = 'symbol';
    const TOK_WHITESPACE = 'whitespace';
    const TOK_KEYWORD = 'keyword';
    const TOK_NUMBER = 'number';
    const TOK_WORD = 'word';

    /** @var Nette\Utils\Tokenizer */
    private $tokens;

    public function __construct()
    {
        $this->tokens = new \Nette\Utils\Tokenizer(array(
            'lineComment' => '(?:\/\/|\#)[^\n]\n',
            'blockComment' => '\/\*(?:.*?)\*\/',
            self::TOK_STRING => \Nette\Latte\Parser::RE_STRING,
            self::TOK_SYMBOL => '[' . preg_quote('-+.,;:?!%&*|=~<>[]{}()^$#/\\', '~') . ']',
            self::TOK_WHITESPACE => '\s+',
            self::TOK_KEYWORD => '(?:do|if|in|for|let|new|try|var|case|else|enum|eval|false|null|this|true|void|with|break|catch|class|const|super|throw|while|yield|delete|export|import|public|return|static|switch|typeof|default|extends|finally|package|private|continue|debugger|function|arguments|interface|protected|implements|instanceof)',
            self::TOK_NUMBER => '\d+(?:\.\d+)?',
            self::TOK_WORD => '\w+',
            'other' => '.' // should never match
        ), 'is');
    }

    /**
     * @param string $js
     * @return string
     */
    public function process($js)
    {
        $this->tokens->tokenize($js);

        $js = '';
        $level = 0;
        $functionScopes = array();
        while ($token = $this->tokens->fetchToken()) {
            if ($token['type'] === self::TOK_SYMBOL) {
                if (in_array($token['value'], array('{', '}'))) {
                    if ($token['value'] === '{') {
                        $js .= $token['value'];
                        $level += 1;
                        if ($level === end($functionScopes)) {
                            $js .= 'try{';
                        }
                    } else {
                        if ($level === end($functionScopes)) {
                            $js .= '}catch(e){e.context=this;console.log(e);throw e;}';
                            array_pop($functionScopes);
                        }
                        $level -= 1;
                        $js .= $token['value'];
                    }
                    continue;
                }
            }
            if ($token['type'] === self::TOK_KEYWORD && $token['value'] === 'function') {
                $functionScopes[] = $level + 1;
            }
            $js .= $token['value'];
        }
        return $js;
    }
}
~~~

Zkoušel jsem to na jQuery, Modernizr i Bootstrapácký js. Tyhle ale upravovat nechci, stačí mi debuggovat můj kód.

Tohle je nejjednodušší způsob jak přiložit preprocessor do aplikace. Lepší by samozřejmě bylo přidrátovat to například do WebLoaderu.

~~~ php
$container->application->onRequest[] = function () {
    if (!file_exists(__DIR__ . '/../www/js/main-preprocessed.js') || !\Nette\Diagnostics\Debugger::$productionMode) {
        $js = file_get_contents(__DIR__ . '/../www/js/main.js');
        $debugger = new JsDebugger;
        file_put_contents(__DIR__ . '/../www/js/main-preprocessed.js', $debugger->process($js));
    }
};
~~~

Z tohoto

~~~ js
$(window).load(function () {
    function Foo(name) {
        this.name = name;
    }
    Foo.prototype.bar = function (lipsum) {
        lipsum.little.error += 1;
    };

    var fu = new Foo("jmeno");
    fu.bar("lorem");
});
~~~

nyní dostanu toto

~~~ js
$(window).load(function () {try{
    function Foo(name) {try{
        this.name = name;
    }catch(e){e.context=this;console.log(e);throw e;}}
    Foo.prototype.bar = function (lipsum) {try{
        lipsum.little.error += 1;
    }catch(e){e.context=this;console.log(e);throw e;}};

    var fu = new Foo("jmeno");
    fu.bar("lorem");
}catch(e){e.context=this;console.log(e);throw e;}});
~~~

Není to sice čarokrásné, ale funguje to.


## Analýza výjimky

Už zbývá jen v produkčním módu vyměnit `console.log` za moji funkci, která bude posílat zanalyzovanou výjimku na server k zalogování.

~~~ js
if (typeof console !== 'object') { console = {}; }
console.dumpTrace = function (e) {
    try {
        var i, calls, call, m,
            exception = {type: e.name, message: e.message, trace: []};
        for (i = 0, calls = e.stack.split(/\n/).slice(1); i < calls.length; i++) {
            m = calls[i].match(/^\s+at\s+(?:(.*)\s+)?\(?(.*?):(\d+):(\d+)\)?$/);
            if (!m) { continue; }
            m = m.concat([null, null, null, null, null]); // prevence chybějících indexů
            call = {call: m[1] ? m[1] : '(anonymous function)', file: m[2], line: parseInt(m[3]), column: parseInt(m[4])};
            if (call.file.indexOf('chrome-extension') !== -1) {continue;}
            exception.trace.push(call);
        }

        console.originalLog(exception);
        // todo: ukládat ajaxem JSON.stringify(exception)
        // kvůli kompatibilitě prohlížečů je možné přilinkovat https://github.com/douglascrockford/JSON-js
    } catch (e) {
        console.originalLog(e);
    }
    return false;
};
console.originalLog = $.proxy(console.log, console);
console.log = function (e) {
    for (var i = 0; i < arguments.length; i++) { console.dumpTrace(e); }
    console.originalLog.apply(arguments);
};
~~~


## Jak by to šlo vylepšit?

V horní ukázce mám schválně vynechané argumenty výjimky, jednotlivých volání a kontext. Jejich převádění na JSON je něco tak neskutenčně komplexního, že mi to za to zkrátka nestojí.

Co Javascript neumí vůbec, tak říct mi, na jakém objektu byla funkce zavolána a jaké proměnné měla kolem sebe (ano, stejně hloupé jako PHP). Jak to dělá Chrome je mi záhadou.

~~~ js
// získání předchozího volajícího ze stack trace
function argsCaller(args) {
    try {
        if (typeof args.callee !== 'undefined' && typeof args.callee.caller !== 'undefined') {
            return args.callee.caller;
        } else {
            return args.caller;
        }
    } catch (undef) {
        return null;
    }
}

// kopie datové struktury objektu
function simplifyObj(obj, depth) {
    if (typeof obj === 'string' || typeof obj === 'number' || typeof obj === 'boolean') { return obj; }
    if (obj instanceof Date) { return obj.toString(); }
    if (depth === 0) { return null; }
    var newObj = obj instanceof Array ? [] : {};
    depth -= 1;
    for (var prop in obj) {
        if (newObj[prop] instanceof Function) {continue;}
        if (!obj.hasOwnProperty(prop)) {continue;}
        newObj[prop] = simplifyObj(obj[prop], depth);
    }
    return newObj;
}

var i, l, calls, call, m,
    exception = {type: e.name, message: e.message, arguments: e.arguments, trace: []};
    caller = argsCaller(argsCaller(arguments).arguments);
for (i = 0, calls = e.stack.split(/\n/).slice(1); i < calls.length; i++) {
    m = calls[i].match(/^\s+at\s+(?:(.*)\s+)?\(?(.*?):(\d+):(\d+)\)?$/);
    if (!m) { continue; }
    m = m.concat([null, null, null, null, null]);
    call = {call: m[1] ? m[1] : '(anonymous function)', file: m[2], line: parseInt(m[3]), column: parseInt(m[4]), args: []};
    if (call.file.indexOf('chrome-extension') !== -1) {continue;}

    if (caller) {
        for (l = 0; l < caller.arguments.length ;l++) { call.args.push(caller.arguments[l]); }
        caller = argsCaller(caller.arguments);
    }
    exception.trace.push(call);
}

console.originalLog(JSON.stringify(simplifyObj(exception, 5)));
~~~

Nad tímhle kusem kódu jsem strávil většinu dne a pokorně to vzdávám. Už nemám ani chuť dodělat to server-side logování. Myslím, že mi Javascript ukradl duši.


A co vy? Logujete chyby v Javascriptu? Já teda asi budu doufat, že mě zachrání tento projekt https://rescuejs.com/
