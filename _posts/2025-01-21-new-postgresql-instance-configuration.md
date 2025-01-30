---
layout: blogpost
title: "New PostgreSQL instance configuration"
permalink: blog/new-postgresql-instance-configuration
date: 2025-01-30 19:00
tag: ["AWS", "PostgreSQL"]
---

You've spent weeks, maybe a few months, developing a prototype locally with a local PostgreSQL in docker, not worrying about a thing.
But now the moment has come, and you must push to prod.
You start an RDS instance, execute the migrations to set up the schema, and launch the app.
It's all good so far!

However, the team has grown from a single lonely wolf, and now you have new colleagues and maybe a few junior devs.
It turns out that if everybody uses the same username and password, it's very hard to find out who left that 5-hour query running that caused the instance to crawl so badly that a full outage would probably be better.
So you give everyone their own user, and for some time, everything works great!
Except on one late Friday night during a routine deployment, the migrations have failed!
What do you mean by "cannot drop X because other objects depend on it"???

Disclaimer: This article does not aim to extensively describe all the best practices, just to set you on the right path and save you from a few ruined Fridays.

<!--more-->

## Roles and ownership basics

The strategy that has worked for me in the past is to set up a few roles that will serve as the bedrock of our permissions.
Instead of giving individual users specific permissions, you'll always give them one of the roles.

Another important rule will be that a single admin role will own all of our database objects; nobody else will own anything.
Technically, PostgreSQL doesn't distinguish between users and roles (a user is just a role that can log in), so here, I mean a logical role that is not somebody's user.

To start, let's say we'll have the following roles, where the `fp_` is the initials of your company to be able to tell where the role came from:
* `fp_role_ro` - a read-only role - useful for tools like [Metabase](https://github.com/metabase/) or a tech-savvy Product Manager
* `fp_role_app` - a role for the common needs of the application, such as modifying data, calling functions, etc. All of that _except_ changing schema. By applying [the Principle of least privilege](https://en.wikipedia.org/wiki/Principle_of_least_privilege), you'll realize that _usually_, most apps don't need to be able to change their schema under normal operations, so by removing that privilege, you're making a potential attack on your system a bit harder.
* `fp_role_admin` - an admin role that can do anything with our database and is the sole owner of all database objects

In PostgreSQL, unless you specify otherwise (and it's annoying to think of it every time), the user creating the object will be its owner.
Sometimes, it's perfectly reasonable (even though it shouldn't be the rule) to execute some schema changes manually, but when you do that, you fragment the ownership, making further changes harder.

This can be solved by having your users always execute `SET ROLE fp_role_admin;` when they open a new session, which is annoying and easy to forget.
Luckily, PostgreSQL enables you to configure this to happen automatically when a user opens a new session.

```sql
ALTER ROLE fp_prochazka_filip SET role TO fp_role_admin;
```

Now, every time I connect to the database, I'll be switched to the `fp_role_admin` role, and any objects I create will be owned by it.
I can still switch to any other role I have access to, but I would have to do that knowingly, and if you're the admin, there is little reason to do so.

This switch only affects permissions and ownership.
Your users will still be nicely visible in the open sessions and running queries list.

Before we jump into defining our roles, there are a few more topics we need to look into.

## The `PUBLIC` role

Let's see [what the PostgreSQL documentation has to say about it](https://www.postgresql.org/docs/17/sql-grant.html#SQL-GRANT-DESCRIPTION-OBJECTS):

> The keyword PUBLIC indicates that the privileges are to be granted to all roles, including those that might be created later. PUBLIC can be thought of as an implicitly defined group that always includes all roles. Any particular role will have the sum of privileges granted directly to it, privileges granted to any role it is presently a member of, and privileges granted to PUBLIC.

This role has, by default, some permissions that might allow the read-only users to do things we don't want them to do, so we will also have to do a reset of the `PUBLIC` role's permissions.

## Settings inheritance basics

[In PostgreSQL, most settings can be overridden in your current session](https://www.postgresql.org/docs/17/config-setting.html#CONFIG-SETTING-SQL).
This means that most settings serve only as defaults.
There are multiple levels of settings defaults:

* System
* Database
* Role
* Role+Database ([see notes here](https://www.postgresql.org/docs/17/sql-alterrole.html#SQL-ALTERROLE-NOTES), highest precedence)

When a new session starts (something connects to a database), these settings are applied as if you've executed `SET parameter TO value`.
This is very useful because you can enforce sane defaults for people and systems that make sense for your application without having to rely on the same people and systems to always apply those defaults at the start of the session.

## `CREATEROLE` vs `SUPERUSER`

[With version 16, the `CREATEROLE` permission got nerfed](https://www.postgresql.org/docs/16/release-16.html#RELEASE-16-PRIVILEGES).
Before, a role with this permission could grant itself to another user, but not anymore.
To grant a role, you must either have the `ADMIN OPTION` for the role or you must be a `SUPERUSER`.

You will likely use the AWS RDS or some other managed service.
Some of the managed services give you full power, and some of them limit you.
AWS RDS is in the group that restricts you a bit - there are things it will not allow you to do.
So, to get the most permissions possible, our admin role has to [inherit from the `rds_superuser` role](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/Appendix.PostgreSQL.CommonDBATasks.Roles.rds_superuser.html).
If you inspect a newly created RDS instance, you might also notice that the `rds_superuser` role does not actually have the `SUPERUSER` permission - it only inherits a bunch of the system roles.

Let's look at a few different behaviors on localhost:

```sql
CREATE ROLE fp_role_admin WITH CREATEROLE INHERIT;
SET ROLE fp_role_admin;
CREATE ROLE second_user INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'secret';
-- [42501] ERROR: permission denied to grant role "fp_role_admin"
-- Detail: Only roles with the ADMIN option on role "fp_role_admin" may grant this role.
```

This is consistent with what we would expect after the change in PostgreSQL 16, let's try adding `SUPERUSER` into the mix:

```sql
CREATE ROLE rds_superuser WITH CREATEROLE SUPERUSER INHERIT;
CREATE ROLE fp_role_admin INHERIT IN ROLE rds_superuser;
SET ROLE fp_role_admin;
CREATE ROLE second_user INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'secret';
-- [42501] ERROR: permission denied to create role
-- Detail: Only roles with the CREATEROLE attribute may create roles.
```

I don't know about you, but this one surprised me - neither the `CREATEROLE` nor the `SUPERUSER` is inherited.
The only way a role can grant itself to another role is if the role itself is directly `SUPERUSER`:

```sql
CREATE ROLE fp_role_admin WITH SUPERUSER;
SET ROLE fp_role_admin;
CREATE ROLE second_user INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'secret';
-- works
```

Now let's look at the RDS example:

```sql
CREATE ROLE fp_role_admin WITH CREATEROLE INHERIT;
SET ROLE fp_role_admin;
CREATE ROLE second_user INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'secret';
-- [42501] ERROR: permission denied to grant role "fp_role_admin"
-- Detail: Only roles with the ADMIN option on role "fp_role_admin_2" may grant this role.
```

This fails consistently with localhost.
Let's try a `SUPERUSER` role:

```sql
CREATE ROLE fp_role_admin WITH SUPERUSER INHERIT;
-- [42501] ERROR: permission denied to create role
-- Detail: Only roles with the SUPERUSER attribute may create roles with the SUPERUSER attribute.
```

As expected based on the RDS documentation.
And what happens if we create a user that inherits `rds_superuser`?

```sql
CREATE ROLE fp_role_admin INHERIT IN ROLE rds_superuser;
SET ROLE fp_role_admin;
CREATE ROLE second_user INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'secret';
-- works
```

What? So `SUPERUSER` is not inherited, but if you inherit `rds_superuser`, suddenly it counts as if you're `SUPERUSER` yourself?
This smells like some RDS customization, but whatever. Let's apply the knowledge.

## The init script for a new database instance

Now that we have the basics covered, we can put together the following block of SQL, which can be used as a template for when you want nicely and consistently defined permissions on your local database and production AWS RDS:

```sql
-- After a new PgSQL RDS instance is created,
-- there is only a single initial user with the LOGIN privilege,
-- and it belongs to the role 'rds_superuser'.

-- First, we ensure the rds_superuser role exists, to be consistent on localhost
DO $body$
BEGIN
    CREATE ROLE rds_superuser WITH INHERIT;
    GRANT pg_monitor,
          pg_read_all_data,
          pg_write_all_data,
          pg_signal_backend,
          pg_checkpoint,
          pg_maintain,
          pg_use_reserved_connections,
          pg_create_subscription TO rds_superuser WITH ADMIN OPTION, INHERIT TRUE;
EXCEPTION
    -- On RDS, the CREATE ROLE will fail with one of the following reasons:
    WHEN SQLSTATE '42501' THEN
        RAISE NOTICE '%, skipping', SQLERRM USING ERRCODE = SQLSTATE;
    WHEN duplicate_object THEN
        RAISE NOTICE '%, skipping', SQLERRM USING ERRCODE = SQLSTATE;
END
$body$;

-- Create an admin role.
-- The admin users will inherit the necessary privileges from this role.
-- This role is meant to own all database objects used in production!
CREATE ROLE fp_role_admin CREATEDB CREATEROLE INHERIT IN ROLE rds_superuser;

-- Make the role a superuser on localhost for consistent behavior with AWS RDS
DO $body$
BEGIN
    ALTER ROLE fp_role_admin WITH SUPERUSER;
EXCEPTION
    -- On RDS, this ALTER will fail with 'Only roles with the SUPERUSER attribute may change the SUPERUSER attribute'
    WHEN SQLSTATE '42501' THEN
        RAISE NOTICE '%, skipping', SQLERRM USING ERRCODE = SQLSTATE;
END
$body$;

-- Allow the initial user to switch to the new role:
GRANT fp_role_admin TO SESSION_USER WITH ADMIN OPTION, INHERIT TRUE;

-- Defaults that are applied when the initial user logins,
-- to ensure nobody will accidentally create objects owned by a different user.
ALTER ROLE SESSION_USER SET role TO fp_role_admin;

-- We will recreate the public schema later, using the correct role
DROP SCHEMA public CASCADE;

-- Database-specific reset
DO $body$
DECLARE
    fp_db_name TEXT := current_database();
BEGIN
    -- The public privileges (all users) are too permissive, we must reset it
    EXECUTE 'REVOKE ALL PRIVILEGES ON DATABASE ' || quote_ident(fp_db_name) || ' FROM PUBLIC';
    EXECUTE 'GRANT CONNECT, TEMPORARY ON DATABASE ' || quote_ident(fp_db_name) || ' TO PUBLIC';

    -- Transfer the ownership of all objects (which should be only the database itself) to the new role:
    EXECUTE 'ALTER DATABASE ' || quote_ident(fp_db_name) || ' OWNER TO fp_role_admin';
END
$body$;

-- This role is going to create tables, so it must grant its own set of default privileges.
SET ROLE fp_role_admin;

-- Recreate the public schema, now under the correct owner
CREATE SCHEMA public;

-- Define role for read-only access
CREATE ROLE fp_role_ro;
GRANT USAGE ON SCHEMA public TO fp_role_ro;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON TABLES TO fp_role_ro;

-- Define role for application's access
CREATE ROLE fp_role_app INHERIT IN ROLE fp_role_ro;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT INSERT, UPDATE, DELETE, TRUNCATE ON TABLES TO fp_role_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO fp_role_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT EXECUTE ON FUNCTIONS TO fp_role_app;
```

On RDS, any user we grant our admin role to will not have the `SUPERUSER` permission, but they'll be as close as possible - they'll be able to manage databases, roles, and permissions freely. And they'll be able to read as much system info as RDS allows.

On localhost, the users granted our admin role will have the `SUPERUSER` permission, resulting in slightly more permissions, but counterintuitively also a better consistency with RDS.

If you want to see what user is connected and what role it's currently "impersonating", you can run the following select:

```sql
SELECT SESSION_USER, CURRENT_USER;
```

## Managing users

Once you execute that big SQL, your next step should be to define the users:

* One user for yourself, so in my case, it would be e.g. `fp_prochazka_filip`
* One admin user for database migrations so that your deployment process is able to change the schema
* One app user for the normal application runtime

Creating users is trivial now:

```sql
-- Create app user
CREATE USER name_of_app_user INHERIT IN ROLE fp_role_app ENCRYPTED PASSWORD 'please-use-password-manager-and-generate-something-random';

-- Create read-only user
CREATE USER name_of_ro_user INHERIT IN ROLE fp_role_ro ENCRYPTED PASSWORD 'please-use-password-manager-and-generate-something-random';

-- Create admin user
CREATE USER name_of_admin_user INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'please-use-password-manager-and-generate-something-random';
ALTER ROLE name_of_admin_user SET role TO fp_role_admin;
```

If you ever need to remove a user, it's also simple:

```sql
DROP ROLE some_user;
```

So with the requirements that we know so far, we would probably create the following users:

```sql
CREATE USER fp_a_developer INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'secret';
ALTER ROLE fp_a_developer SET role TO fp_role_admin;

CREATE USER fp_metabase INHERIT IN ROLE fp_role_ro ENCRYPTED PASSWORD 'secret';

CREATE USER fp_app_runtime INHERIT IN ROLE fp_role_app ENCRYPTED PASSWORD 'secret';

CREATE USER fp_app_migrations INHERIT IN ROLE fp_role_admin ENCRYPTED PASSWORD 'secret';
ALTER ROLE fp_app_migrations SET role TO fp_role_admin;
```

## Timeouts basics

Before we dive into configuring the timeouts, let's first take a look at what [the official documentation](https://www.postgresql.org/docs/17/runtime-config-client.html), has to say about timeouts:

* `statement_timeout` -  Abort any statement that takes more than the specified amount of time. ... A value of zero (the default) disables the timeout.
* `transaction_timeout` (since 17.0) - Terminate any session that spans longer than the specified amount of time in a transaction. ... A value of zero (the default) disables the timeout.

So _by default_, you can shoot off a query and let it run for eternity.
Usually, you won't notice this during development or early phases of the project because you don't have a lot of data.
Only after your database grows can it lead to nasty problems if you don't have your queries finishing quickly.

To test things, we can quickly spin up an instance of the latest PostgreSQL using `docker compose up -d experiments` with the following config:

```yml
services:
  experiments:
    image: postgres:17.2-alpine
    environment:
      - POSTGRES_DB=experiments
      - POSTGRES_USER=user
      - POSTGRES_PASSWORD=secret
    ports:
      - "127.0.0.1:5432:5432"
```

And then you can play around with the various settings levels, for example:

```sql
-- database level timeout
ALTER DATABASE "experiments" SET statement_timeout='10min';
-- user-level timeout
ALTER ROLE "fp_a_developer" SET statement_timeout='5min';
```

Then, you can log in as one of the users we've defined before and see how it affects the timeout you get in a new session.
You could try running a slow query and watching it get killed after the timeout, but it's probably faster to run `SHOW statement_timeout;` to get the final value.

And, of course, there are valid use cases for long-running statements and transactions.
So, if you run into them, you can adjust the timeouts on the session level.
Once the session ends, the next session will have the configured default.

```sql
-- session level timeout
SET statement_timeout='1h';
```

I'll give you some spoilers that you can find out by experimenting:
* The precedence is intuitive: system < database < role < role+database < session
* The settings you configure for roles (that other users inherit) are ignored; only settings for the session_user (the user you use for the login) affect the session. I know, bummer.

## Configuring timeouts

Given the information we now know, how should we configure the timeouts? I'd start by collecting more requirements, and I'll give you an example:

* The app is a web-based application with some background processing
* Most "transactions" come from the HTTP request, and the load balancer or proxy in front of the application is configured to timeout requests after 5min.
* Our application web server has 1min timeout configured for HTTP requests, but the ideal response time is 1ms.
* Our background processes may run longer queries.
* We don't want our developers to accidentally run a query that eats too many resources. Quick queries are fine. Running slow queries is not ideal, but we won't try to prevent it if it's intentional.
* We want to be able to connect to the database from Metabase to allow the creation of dashboards on the live database. This is risky, but it saves a lot of time by not having to create fully-featured dashboards in our admin interface.

Given the 1min requirement for HTTP requests, we should be strict with the database defaults.

```sql
ALTER DATABASE "experiments" SET statement_timeout='15s';
```

Next is the migration user.
As you probably know, migrations can take very long - not ideal, but they warrant a big safety buffer:

```sql
ALTER ROLE fp_app_migrations SET statement_timeout='1d';
```

We don't want Metabase draining our resources, but we can tolerate reasonably slow queries.

```sql
ALTER ROLE fp_metabase SET statement_timeout='5min';
```

Next, we should consider creating more "app runtime" users to separate the HTTP traffic from the cron jobs and message consumers that might run longer.
Not only can you then centrally configure more granular timeouts, but you'll also get better visibility in the running queries overview.

If you're using stuff like Spring Boot and its `@Scheduled` jobs within the web server, you'll share one database connection pool between the HTTP requests and background jobs.
This is a perfectly reasonable way to start because it simplifies the rest of the infrastructure but makes separating users hard.
If your background jobs are fast, then you're good with just one user, and the occasional slow job can increase the statement timeout for its transaction with the `SET` command we've seen previously.

But let's say for the sake of the example, we want them also separated, so instead of having just one `fp_app_runtime`, we'll split it into three users:

```sql
-- the role fp_app_runtime_http takes the 15sec default from database level, so we won't be setting the defaults
ALTER ROLE fp_app_runtime_cron SET statement_timeout='5min';
ALTER ROLE fp_app_runtime_consumers SET statement_timeout='5min';
```

With all this done, let's look at what `SHOW statement_timeout` for each user returns:

* `fp_a_developer` - has the database default of `15s`
* `fp_metabase` - has its own default of `5min`
* `fp_app_migrations` - has its own default of `1d`
* `fp_app_runtime_http` - has the database default of `15s`
* `fp_app_runtime_cron` - has its own default of `5min`
* `fp_app_runtime_consumers` - has its own default of `5min`

Looks good! But remember, your app might have different requirements, so if you arrive at different timeouts, it's probably not wrong either.

## Conclusion

We've defined the roles we'll need, cleaned up the default permissions, and configured some sane timeouts to prevent disasters.
By defining granular users, we've also gained relatively good visibility into who's running what.

What next? If the project warrants it, you might want to think about more granular admin permissions - separating user management from schema changes.
Later down the line, you might realize you need even better user management and auditing of who is doing what - at that point, looking into [Teleport](https://goteleport.com/docs/enroll-resources/database-access/) or a similar tool makes sense.
But that's all out of the scope of this already lengthy article :)

Have you also run into object ownership shenanigans or issues related to infinite default timeouts yourself? How did you solve it?
