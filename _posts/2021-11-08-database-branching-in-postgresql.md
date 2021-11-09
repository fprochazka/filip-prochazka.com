---
layout: blogpost
title: "Database branching (just like with git) in PostgreSQL"
permalink: blog/database-branching-in-postgresql
date: 2021-11-08 22:00
tag: ["PostgreSQL"]
---

Imagine you're developing a feature, which requires you to do some database schema changes, and those changes might not be backward compatible.
But right now, you just want to develop your feature and worry about migrating the production later. You start by writing the code and gradually ALTERing your local database.

But after some time, priorities change. Either the product owner changes his mind and forces you to start working on something else, or there is a critical bug on production that
must be fixed immediately. Anyway, you have to switch to master and start working on something else. But you've made a mess of your local database because you've thought you'd be
able to finish this before you move on to something else, and now your master won't work with whatever is in your local database.

What now? If only you could just magically revert the database to where it was before you've started changing the schema.

<!--more-->
## Branching the database

Sadly, PostgreSQL does not have a real branching feature. But it has something that can be used as such -
the [CREATE DATABASE](https://www.postgresql.org/docs/13/sql-createdatabase.html) command. It allows you to copy the entire database with all its data via a single command, and it
does so really fast. It has only one limitation - there can be no active connection to the database you're copying, but that's not an issue on your localhost.

~~~sql
CREATE DATABASE app_copy WITH TEMPLATE app OWNER a_user;
~~~

To work around the limitation, I always keep a separate connection that I can use for the databases manipulation. Unlike MySQL, PostgreSQL requires a database name for a
connection, and it simply won't connect unless you give it one, so you cannot connect to "nothing" to manipulate the databases. But there is a simple workaround - most PostgreSQL
instances have a default database named `postgres`. If you connect to this database and disconnect from all the others, you can safely copy and drop your application databases. You
can also create an empty dummy database just for this.

With this tool in hand, let us look at the "branching strategy".

## Backups strategy

As with any branching strategy, a bit of discipline is required to make it work. Simply put, you're always working on the same database, but you create backups to have something to
go back to when needed.

First, you configure your app to connect to the database `app` as you usually would. You only create a backup once you face the need to make schema changes that might be considered
incompatible.

~~~sql
CREATE DATABASE app_backup_name WITH TEMPLATE app OWNER a_user;
~~~

And then you continue with your work. If you're able to work uninterrupted, you'll just finish your feature and drop the backup.

~~~sql
DROP DATABASE app_backup_name;
~~~

But, if you happen to need to go back, either you can configure your app to connect to the backup, or you can restore it to main.

~~~sql
ALTER DATABASE app RENAME TO app_feature_backup;
ALTER DATABASE app_backup_name RENAME TO app;
~~~

This little dance is a bit weird, but that's because the strategy is optimizing the happy path (that you won't need the backup), which makes the restore itself a bit more work.

You can obviously create much more complex strategies. But this basic strategy is good enough for my everyday use since I'm trying to never work on more features simultaneously,
which helps prevent nasty conflicts.

## Why didn't you just ...?

My local database of an app where I'm utilizing this technique has around 130GB, and it is not trivial to initialize it for reasons that are out-of-scope of this article.
What are the alternatives?

* regular filesystem copy&paste: 4-5min (if you want to switch the databases, you have to turn off the server and switch files, or start a new instance and reconfigure your app)
* `CREATE DATABASE`: 5-6min
* dump&restore: I'm not going to even try, but it's going to be at least 1 hour

As you can see, `CREATE DATABASE` combines speed and simplicity and is ideal for this use case.
