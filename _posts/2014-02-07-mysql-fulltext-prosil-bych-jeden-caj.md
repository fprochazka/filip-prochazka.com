---
layout: blogpost
title: "MySQL fulltext: prosil bych jeden čaj"
permalink: blog/mysql-fulltext-prosil-bych-jeden-caj
date: 2014-02-07 20:00
tag: ["PHP", "MySQL", "Fulltext"]
---

Mějme tabulku jídel na kterou chceme napsat hledání.

~~~ sql
CREATE TABLE `food` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `description` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

INSERT INTO `food` (`name`, `description`) VALUES
('Semtex', 'energiťák'), ('Čaj', 'heřmánkovej');
~~~

Máme dvě možnost, buďto použijeme search engine (ElasticSearch, Sphinx, ...) nebo se s tím budeme srát v MySQL.
No a aby to bylo zajímavé, tak se s tím pojďme srát :)


## InnoDB neumí FULLTEXT index

První problém, jak ho vyřešit? Triggery.

Takže si vytvoříme tabulku do které budeme duplikovat data (což je v podstatě to stejné co byste dělali s externí službou na hledání)

~~~ sql
CREATE TABLE `food_fulltext` (
  `food_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `description` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`food_id`),
  FULLTEXT KEY `name_description` (`name`,`description`),
  FULLTEXT KEY `name` (`name`),
  FULLTEXT KEY `description` (`description`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
~~~

A napíšeme si triggery

~~~ sql
DELIMITER ;;

CREATE PROCEDURE `food_fulltext_update` (IN `updated_id` int(11))
BEGIN
    DECLARE `name` TEXT ;
    DECLARE `description` TEXT ;

    SELECT food.`name`, food.`description` INTO `name`, `description`
    FROM food WHERE `id` = `updated_id`;

    INSERT INTO `food_fulltext` (`food_id`, `name`, `description`) VALUES (`updated_id`, `name`, `description`)
    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
END;;

CREATE TRIGGER `food_ai` AFTER INSERT ON `food` FOR EACH ROW
IF @disable_triggers IS NULL THEN
    CALL food_fulltext_update(NEW.`id`);
END IF;;

CREATE TRIGGER `food_au` AFTER UPDATE ON `food` FOR EACH ROW
IF @disable_triggers IS NULL THEN
    CALL food_fulltext_update(NEW.`id`);
END IF;;

CREATE TRIGGER `food_ad` AFTER DELETE ON `food` FOR EACH ROW
IF @disable_triggers IS NULL THEN
    DELETE FROM food_fulltext WHERE `food_id` = OLD.`id`;
END IF;;

DELIMITER ;

INSERT INTO `food_fulltext` (`food_id`, `name`, `description`)
SELECT food.`id`, food.`name`, food.`description` FROM food
~~~

Ta proměnná `@disable_triggers` je vychytávka, aby se daly triggery vypnout při hromadných operacích nad daty.
Trigger může jednoduchý update spomalit klidne i exponenciálně (fakt se to hodí mít možnost vypnout).

Fajn, takže při zápisu do tabulky s jídly se nám data zprelikují pod fulltext a můžeme hned začít s hledáním.


## Hledáme Semtex

Takhle nějak by mohla vypadat search query ([inspirovaná článkem od Jakuba](http://php.vrana.cz/fulltextove-vyhledavani-v-mysql.php))

~~~ sql
SELECT food_id FROM food_fulltext
WHERE MATCH(name, description) AGAINST (? IN BOOLEAN MODE)
ORDER BY 5 * MATCH(name) AGAINST (?) + MATCH(description) AGAINST (?) DESC
LIMIT 1000
~~~

a když ji pak proženeme přes `Nette\Database\Context`

~~~ php
function search($string)
{

    $sql = "...";

    return $this->db->query($sql, $string, $string, $string)->fetchAll();
}
~~~

s hledaným výrazem od uživatele

~~~ php
dump($fulltext->search("Semtex")); // [['food_id' => 1]]
~~~

Super, našli jsme Semtex!


## Hledáme Čaj

Jenže když dáme hledat čaj tak máme problém (konkrétně dva)

~~~ php
dump($fulltext->search("Čaj")); // []
~~~

Ten první je, že mysql má výchozí minimální délku slova pro fulltext větší než 3,
to se dá změnit celkem snadno

~~~ shell
$ sudo nano /etc/mysql/my.cnf
~~~

~~~ ini
[mysqld]
# Fine Tuning
ft_min_word_len = 3
~~~

~~~ shell
$ sudo service mysql restart
~~~

~~~ sql
REPAIR TABLE `food_fulltext` QUICK;
~~~

Ten druhý problém je, že dost agresivně zohledňuje diakritiku,
takže na dotaz `"Čaj"` se nám sice vrátí výsledek, ale na dotaz `"caj"` se nevrátí nic.


## Zbavujeme se diakritiky

Protože chceme mít proces automatický, abychom nemuseli řešit ukládání do dvou tabulek, tak máme triggery.
A protože máme triggery, musíme dělat konverzi na úrovni databáze.

~~~ sql
DELIMITER ;;

--
-- https://github.com/falcacibar/mysql-routines-collection/blob/28ef383092ffa5a0e4e7e377fa5d1a3badcc488c/tr.func.sql
-- @author Felipe Alcacibar <falcacibar@gmail.com>
--
CREATE FUNCTION `strtr`(`str` TEXT, `dict_from` VARCHAR(1024), `dict_to` VARCHAR(1024)) RETURNS text LANGUAGE SQL DETERMINISTIC NO SQL SQL SECURITY INVOKER COMMENT ''
BEGIN
    DECLARE len INTEGER;
    DECLARE i INTEGER;

    IF dict_to IS NOT NULL AND (CHAR_LENGTH(dict_from) != CHAR_LENGTH(dict_to)) THEN
        SET @error = CONCAT('Length of dicts does not match.');
        SIGNAL SQLSTATE '49999'
            SET MESSAGE_TEXT = @error;
    END IF;

    SET len = CHAR_LENGTH(dict_from);
    SET i = 1;

    WHILE len >= i  DO
        SET @f = SUBSTR(dict_from, i, 1);
        SET @t = IF(dict_to IS NULL, '', SUBSTR(dict_to, i, 1));

        SET str = REPLACE(str, @f, @t);
        SET i = i + 1;

    END WHILE;

    RETURN str;
END;;

CREATE FUNCTION `to_ascii`(`str` TEXT) RETURNS text LANGUAGE SQL DETERMINISTIC NO SQL SQL SECURITY INVOKER COMMENT ''
BEGIN
    RETURN strtr(LOWER(str), 'áäčďéěëíµňôóöŕřšťúůüýžÁÄČĎÉĚËÍĄŇÓÖÔŘŔŠŤÚŮÜÝŽ', 'aacdeeeilnooorrstuuuyzaacdeeelinooorrstuuuyz');
END;;
~~~

Upravíme proceduru která synchronizuje fulltext

~~~ sql
DELIMITER ;;

DROP PROCEDURE `food_fulltext_update`;;
CREATE PROCEDURE `food_fulltext_update` (IN `updated_id` int(11))
BEGIN
    DECLARE `name` TEXT ;
    DECLARE `description` TEXT ;

    SELECT to_ascii(food.`name`), to_ascii(food.`description`) INTO `name`, `description`
    FROM food WHERE `id` = `updated_id`;

    INSERT INTO `food_fulltext` (`food_id`, `name`, `description`) VALUES (`updated_id`, `name`, `description`)
    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);
END;; -- 0.001 s
~~~


A ještě upravíme zpracování vstupu do SQLka

~~~ php
use Nette\Utils\Strings

function search($string)
{
    $string = Strings::lower(Strings::normalize($string);
    $string = Strings::replace($string, '/[^\d\w]/u', ' ');

    $words = Strings::split(Strings::trim($string), '/\s+/u');
    $words = array_unique(array_filter($words, function ($word) {
        return Strings::length($word) > 1;
    }));
    $words = array_map(function ($word) {
        return Strings::toAscii($word) . '*';
    }, $words);

    $string = implode(' ', $words);

    $sql = "...";

    return $this->db->query($sql, $string, $string, $string)->fetchAll();
}
~~~


## Našli jsme čaj!

Už jenom otestovat

~~~ php
dump([
    $fulltext->search("Čaj"),
    $fulltext->search("Caj"),
    $fulltext->search("čaj"),
    $fulltext->search("caj"),
]); //  [['food_id' => 2]], [['food_id' => 2]], [['food_id' => 2]], [['food_id' => 2]]
~~~

a máme to hotovo. Doufám že tohle je naposledy co jsem musel řešit fulltext v MySQL a vám to přeji taky ;)
