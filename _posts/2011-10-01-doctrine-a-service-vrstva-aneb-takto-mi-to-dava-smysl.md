---
layout: blogpost
title: "Doctrine a service vrstva aneb takto mi to dává smysl"
permalink: blog/doctrine-a-service-vrstva-aneb-takto-mi-to-dava-smysl
date: 2011-10-01
tag: ["Doctrine"]
---

Webexpo 2011 bylo velice inspirativní. S Patrikem jsme si v sobotu večer otevřeli notebooky a konečně jsme se odhodlali převést myšlenky v realitu. Měl jsem vcelku jasnou vizi, kterou mi Patrik pomohl dopilovat k dokonalosti.


## Repozitáře nestačí

Repozitář je, podle definice, jenom taková chytřejší kolekce. Je potřeba entity i ukládat a mazat, nevidím důvod, proč by to nemohl dělat ten stejný objekt. Vznikl tedy `Dao`, nebo-li [Data-Access-Object](https://en.wikipedia.org/wiki/Data_access_object).

`Dao` implementuje několik samostatných rozhraní a rozšiřuje repozitář

~~~ php
class Dao extends Doctrine\ORM\EntityRepository
implements IDao, IQueryable, IObjectFactory
~~~

V `Doctrine\ORM\EntityRepository` je základní funkčnost jako `findAll()`, `find()`, ... vždyť to znáte.

`IDao` definuje metody `save()` a `delete()`, které umí pojmout i pole nebo kolekce, pro snadnější manipulaci s entitami. Vychází z rozhraní `IQueryExecutor`, ale o tom až za chvíli

`IQueryable` definuje metody `createQueryBuilder()` a `createQuery()`. Ta první již je v repozitáři, ale ta druhá je jenom v `EntityManager`u. Potřebné jsou ale obě dvě.

A `IObjectFactory` definuje jednoduchou metodu `createNew()`, pro vytváření nových instancí entit.

Zatím vcelku jednoduché, že? Pár metod navíc pro repozitář, aby se s ním lépe pracovalo. Tuto třídu `Dao` nastavuji a vynucuji pomocí listeneru při načítání metadat. Takže všechny entity ji mají v základu místo obyčejného repozitáře.

Zde bych doporučil článek Honzy Tichého [Pět vrstev modelu](http://www.phpguru.cz/clanky/pet-vrstev-modelu) a související diskuzi.


## Tolik metod musí stačit

`Dao` třída nám pěkně nabobtnala a umí toho tak akorát. Kdybych se měl držet myšlenek svého článku v Nette kuchařce [ERM](https://wiki.nette.org/cs/cookbook/model-entity-repository-mapper), tak bych nyní, pro specifické dotazy, třídu `Dao` dědil a přidával jí metody pro jednotlivé DQL. Metody jako `findBarByBazAndOrderItByFoo()` rychle přibývají a objekt těžkne, ztrácí řád a vůbec toho umí nějak moc.

Zde přichází na řadu Aleš Roubíček s článkem [Doménové dotazy](https://rarous.net/weblog/377-domenove-dotazy.aspx), který mi připomněl dávno zapomenuté články od Fowlera. V podstatě je to kuchařka na samostatné třídy pro DQL. Možná je to v jiném jazyce, ale je to snadno pochopitelné, takže tuto část do hloubky rozebírat nebudu a poprosím Vás odskočit si na jeho článek pro detaily a chybějící souvislosti.

Definoval jsem si tedy rozhraní `IQueryObject` a `IQueryExecutor`, kterému Query objekty předávám a získávám tak výsledek. Když teď chci zapsat dotaz, tak si podědím abstraktní `QueryObjectBase`, která už rozhraní `IQueryObject` implementuje a implementuji metodu `doCreateQuery()`, kterou vyžaduje abstraktní předek.

~~~ php
class RolePermissionsQuery extends QueryObjectBase
{
    /** @var Role */
    private $role;

    /**
     * @param Role $role
     */
    public function __construct(Role $role)
    {
        $this->role = $role;
    }

    /**
     * @param IQueryable $dao
     * @return Doctrine\ORM\QueryBuilder
     */
    protected function doCreateQuery(IQueryable $dao)
    {
        return $dao->createQueryBuilder('perm')->select('perm', 'priv', 'act', 'res')
            ->innerJoin('perm.privilege', 'priv')
            ->innerJoin('perm.role', 'role')
            ->innerJoin('priv.action', 'act')
            ->innerJoin('priv.resource', 'res')
            ->where('role = :role')->setParameter('role', $this->role);
    }
}
~~~

A získávám výsledek zjednodušeně takto

~~~ php
$role = $em->getRepository('Role')->find(1);
$result = $em->getRepository('Permission')
    ->fetch(new RolePermissionsQuery($role));
~~~

Nemusím tedy znečišťovat `Dao` dalšími metodami, protože všechno mám rozdělené na třídy.

<del>A ještě bych upozornil, že Doctrine mám obaleno v samostatném DI Containeru (subcontainer), takže na `EntityManager` se snažím nesahat, volám si přes zkratku v něm jenom jednotlivé `Dao`.</del>


## Kam se poděly service?

Někdo možná špatně pochopil, že ty service, to jsou vlastně třídy, které mají metody `save()`, `delete()` a dáváme jim repozitář. Taky jsem to tak chvilku zkoušel, ale to mi prostě nedává smysl.

`Dao` a Query objekty perfektně obslouží persistenci a načítání entit a service už je všechno ostatní. Validační pravidla, počítaní, nějaké managery, ... Zkrátka všechny třídy, které nezajímá, že někde je nějaká databáze a operují s logikou, kterou je naučíte.


## Co jsem tím získal?

- Zvrácenou radost, že skoro vůbec nepotřebuji `EntityManager`. <del>Pouze na získávání metadat.</del>
- Nemusím rozšiřovat `Dao`, abych jim vnucoval metody na složité dotazy.
