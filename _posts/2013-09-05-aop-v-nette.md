---
layout: blogpost
title: "Aop v Nette Frameworku"
permalink: blog/aop-v-nette
date: 2013-09-05 20:30
tag: ["Nette Framework", "PHP", "Kdyby", "Symfony", "Doctrine"]
---

Znáte termín [Aspektově orientované programování](http://cs.wikipedia.org/wiki/Aspektov%C4%9B_orientovan%C3%A9_programov%C3%A1n%C3%AD?)
Stejně jako u "Kdyby/Events":/blog/eventy-a-nette-framework, pointou je rozbít systém na menší logické celky, ovšem každý přístup to dělá maličko jinak.

Hranice mezi Eventy a AOP je strašlivě tenká a rozhodnout se který přístup v konkrétním případě použít nemusí být vůbec lehké.
A aby to náhodou nebylo moc jednoduché, tak Eventy jsou teoreticky nahraditelné AOPčkem, ale naopak to nejde.

AOP má simulovat skládání různých chování (behaviour) do jednoho objektu bez mnohonásobné dědičnosti z venku, aniž by o tom tento objekt věděl.
Kdežto událostí je si sám vědom, protože to on je vyvolává, ale už neví o listenerech, které na ně naslouchají.

<!--more-->
## Ale proč vlastně?

AOP mě vždycky fascinovalo a chtěl jsem mít možnost si ho konečně vyzkoušet. A jak se naučit AOP lépe, než když napíšu a budu udržovat vlastní rozšíření do Nette?

Má plno skvělých využití. Od debugovacích a logovacích nástrojů až po celé aplikační moduly, které se můžou navzájem skvěle rozšiřovat.


## A co to teda umí?

Hned na začátek bych se měl přiznat, že jsem prachsprostě obšlehl Flow3, protože ten je nejblíže mému ideálu.
Pokud Vám bude v článku nebo v mé dokumentaci něco nejasného, [sločte si do dokumentace ke Flow](http://docs.typo3.org/flow/TYPO3FlowDocumentation/TheDefinitiveGuide/PartIII/AspectOrientedProgramming.html),
až na drobné detaily je chování téměř identické.

Asi nejlepší bude ukázat si to na živém kódu, pojďme si například trošku vyčistit třídu `Kdyby\Translation\Translator`,
která nám [kvůli sbírání informací pro debug panel nepěkně nabobtnala](https://github.com/Kdyby/Translation/blob/4ab9918c56efe97a800d2b8dd53ed238bc410d65/src/Kdyby/Translation/Translator.php).

Jen kvůli debugovacím informacím do panelu překrývám metody a mám v nich takovéto podmínky

~~~ php
if ($this->panel !== NULL && $id === $result) { // probably untranslated
	$this->panel->markUntranslated($id);
}
~~~

Jak by to vypadalo, kdybych tohle všechno přenesl do aspektu?

~~~ php
use Kdyby\Aop;
use Kdyby\Aop\JoinPoint;

class TranslatorPanelAspect extends Nette\Object
{
	/** @var \Kdyby\Translation\Diagnostics\Panel */
	private $panel;

	public function __construct(\Kdyby\Translation\Diagnostics\Panel $panel)
	{
		$this->panel = $panel;
	}

	/**
	 * @Aop\Before("method(Kdyby\Translation\Translator->translate) && setting(%debugMode% == TRUE)")
	 */
	public function translate(JoinPoint\BeforeMethod $before)
	{
		$message = $afterReturning->arguments[0]; // first argument

		if ($message instanceof Nette\Utils\Html) {
			$this->panel->markUntranslated($message);
		}
	}

	/**
	 * @Aop\AfterReturning("method(Kdyby\Translation\Translator->trans) && setting(%debugMode% == TRUE)")
	 */
	public function trans(JoinPoint\AfterReturning $afterReturning)
	{
		$id = $afterReturning->arguments[0]; // first argument
		$result = $afterReturning->getResult();

		if ($id === $result) { // probably untranslated
			$this->panel->markUntranslated($id);
		}
	}

	/**
	 * @Aop\Around("method(Kdyby\Translation\Translator->transChoice) && setting(%debugMode% == TRUE)")
	 */
	public function transChoiceDebug(JoinPoint\AroundMethod $around)
	{
		$id = $around->arguments[0]; // first argument

		try {
			$result = $around->proceed();

		} catch (\Exception $e) {
			$result = $id;
			$this->panel->choiceError($e);
		}

		if ($id === $result) { // probably untranslated
			$this->panel->markUntranslated($id);
		}

		return $result;
	}

}
~~~

Tohle by mohlo posloužit jako výborný základ, teď si aspekt registruji do configu

~~~ neon
aspects:
	- TranslatorPanelAspect
~~~

A můžu vyčistit `Translator`. Nebudu ho sem kopírovat celý, pouze metody co by se změnily.

~~~ php
class Translator extends BaseTranslator implements Nette\Localization\ITranslator
{

	// smažu $panel

	// ...

	// smažu metodu injectPanel

	public function translate($message, $count = NULL, array $parameters = array(), $domain = NULL, $locale = NULL)
	{
		if (empty($message)) {
			return $message;

		} elseif ($message instanceof Nette\Utils\Html) {
			// tady už nemusím volat panel
			return $message;
		}

		// ...
	}

	// metodu trans už nemusím vůbec dědit

	// metodu transChoice už nemusím vůbec dědit

	// ...

}
~~~

Z `Translator` úplně vypadl panel a tím i zodpovědnost o kterou se nyní vůbec nemusí starat.


## Debugovacími nástroji to nekončí...

Co si takhle napsat nástroj, který nám bude umět přepisovat parametry v presenteru na entity?
Presentery už nejakou dobu jdou vytvářet přes DI Container, takže by to neměl být problém.

~~~ php
class EntityParametersAspect extends Nette\Object
{

	/**
	 * @Aop\Before("method(Nette\Application\UI\Presenter->[render|action|handle]*())")
	 */
	public function process(JoinPoint\BeforeMethod $before)
	{
		$arguments = $before->getArguments(); // argumenty metody
		$refl = $before->getTargetReflection(); // reflexe metody

		// přečtu anotace metody, abych zjistil typy
		foreach ($ref->getParameters() as $i => $parameter) {
			if (/* parametr nema definovany typ entity */) {
				continue;
			}

			if ($entity = $this->entityManager->find($entityClass, $arguments[$i])) {
				$before->setArgument($i, $entity);

			} else {
				$before->setArgument($i, NULL);
			}
		}
	}

}
~~~

Trošku doladit a mám a automatickou konverzi na entity v celém systému.
(Disclaimer: konkrétně tento example by mohl trochu zlobit, protože nette kontroluje typy argumentů, berte to jako ilustraci, nikoliv finální řešení)


## Ale jak je tohle možné?

Celé Kdyby/Aop stojí na velice jednoduchém principu. Třídu podědím, metody překryju a do cache vygeneruju kód, který volá metody aspektů.
Není tedy (zatím) možné překrývat finální metody ani třídy. Funguje to pouze na "veřejných"((public)) nebo "chráněných"((protected)) metodách.


## Vyzkoušíte se mnou Kdyby/Aop?

Pokud ano, tak berte prosím na vědomí, že toto rozšíření je pořád prorotyp a bude se následující dny aktivně vyvíjet.
Je potřeba dodělat ještě několik funkcí (introductions například), napsat více testů a hlavně pořádně to zkoušet v živých aplikacích.

Podrobnou dokumentaci [najdete již klasicky u rozšíření](https://github.com/Kdyby/Aop/blob/master/docs/en/index.md).

Pokud tedy rádi žijete na hraně, [reportujte prosím všechny chyby co najdete na github](https://github.com/Kdyby/Aop/issues), děkuji!
