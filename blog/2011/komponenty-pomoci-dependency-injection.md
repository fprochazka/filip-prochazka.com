Date: 2011-08-01
Tags: Nette Framework

# Komponenty pomocí Dependency Injection

Od té doby, co máme v Nette předělaný Dependency Injection Container, vynořily se hromady dotazů: "Jak dostanu do formuláře translator?", nebo: "Jak mám získat v komponentě nějaké řádky z databáze?" Ve skutečnosti je to velice snadné. Stačí si uvědomit, že služby nejsou to jediné, co může v konstruktoru vyžadovat jiné služby.


## Články pomocí komponent

Ale pěkně od začátku. Jak tedy, úplně nejjednodušeji, získám například záznam z databáze? Protože dibi všichni známe, budu předpokládat, že máme jako službu `@db`, instanci `DibiConnection`. Nejprve po staru.

```php
class ArticleControl extends Nette\Application\UI\Control
{
	public function render($id)
	{
		$this->template->article = $this->presenter->context->db->query("SELECT * FROM articles WHERE id = %i", $id);
		$this->template->render();
	}
}
```

Snadné, že? Ale moc to nedodržuje princip Dependency Injection. Pokud by taková komponenta už v konstruktoru přijala `@db`, tak si na něj nemusí sahat přes presenter. Proč je takový přístup lepší si můžete přečíst v [dokumentaci Nette](http://doc.nette.org/cs/dependency-injection) a nebo na [Zdrojáku](http://zdrojak.root.cz/serialy/jak-na-dependency-injection/)

```php
class ArticleControl extends Nette\Application\UI\Control
{
	/** @var DibiConnection */
	private $db;

	public function __construct(DibiConnection $db)
	{
		parent::__construct(); // tenhle řádek je velice důležitý
		$this->db = $db;
	}

	public function render($id)
	{
		$this->template->article = $this->db->query("SELECT * FROM articles WHERE id = %i", $id);
		$this->template->render();
	}
}
```

To je mnohem lepší. Ale protože naše aplikace využívá modely jako logickou vrstvu, dotaz přesuneme do modelu.

```php
class Articles extends Nette\Object
{
	/** @var DibiConnection */
	private $db;

	public function __construct(DibiConnection $db)
	{
		$this->db = $db;
	}

	public function find($id)
	{
		return $this->db->select('*')->from('articles')->where('id = %i', $id)->fetch() ?: NULL;
	}
}
```

Kód byl přesunut do modelu, kam patří. Zároveň bylo zaručeno, že když dibi nic nenajde, tak vrátí `NULL`. A teď zbývá model použít v komponentě.

```php
class ArticleControl extends Nette\Application\UI\Control
{
	/** @var Articles */
	private $articles;

	public function __construct(Articles $articles)
	{
		parent::__construct();
		$this->articles = $articles;
	}

	public function render($id)
	{
		$this->template->article = $this->articles->find($id);
		$this->template->render();
	}
}
```

Jak by teď vypadala šablona téhle komponenty?

```html
{if $article}
	<h1>{$article->title}</h1>
	<div class="article">{$article->content}</div>
	...
{else}
	<p>Je nám líto, ale článek neexistuje, nebo byl smazán.</p>
{/if}
```

Díky tomu, že model vrací `NULL`, když nic nenajde a dibi umírá víceméně pouze na syntaktických chybách, nikdy nemůže vzniknout jiný stav, než že článek buď existuje a model ho najde a vrátí, nebo neexistuje a vrátí pouze `NULL`. Nemusíme se proto bát, že by vyskočila chyba a přestala se nám načítat stránka, uprostřed vykreslování šablony.


Takovouto komponentu si teď můžeme připojit do presenteru pomocí továrničky a předáme jí model, který máme zaregistrovaný jako službu pod názvem `articles`. Je samozřejmě možné použít i nějaký [model loader](http://wiki.nette.org/cs/cookbook/dynamicke-nacitani-modelu).

```php
protected function createComponentArticle()
{
	return new ArticleControl($this->context->articles);
}
```

Vykreslit takovouto komponentu jde pak velice snadno, třeba takto

```html
{control article $presenter->id}
```

Příklad počítá s tím, že v persistentním parametru presenteru jménem `$id` bude ID článku. Pro úplnost, na takový presenter budeme generovat odkaz nejjednodušeji takto:

```html
{link Article:show id => $article->id}
```


## Služby v továrničkách

A co teď s tím translatorem v tom formuláři? To je snadné, prostě mu ho předáme!

```php
protected function createComponentUserForm()
{
	$form = new Nette\Application\UI\Form();
	$form->addText('name', 'Name');
	$form->addText('surname', 'Surname');

	// předáme translator
	$form->setTranslator($this->context->translator);

	// připojíme formulář
	return $form;
}
```

A je vystaráno! Už nikdy žádný `Environment`! :)

## Kam dál?

Více o vytváření modelů v dibi si můžete přečíst v článku [Model: Entity-Repository-Mapper](http://wiki.nette.org/cs/cookbook/model-entity-repository-mapper). Velice zajímavá debata o ORM pro Nette [je i na fóru](http://forum.nette.org/cs/7328-hledani-nette-like-orm-pro-php) a pokud by někoho zajímalo více o NotORM může pokračovat v podnětném čtení v [dalším vláknu](http://forum.nette.org/cs/8389-petivrstvy-model-postaveny-na-notorm)
