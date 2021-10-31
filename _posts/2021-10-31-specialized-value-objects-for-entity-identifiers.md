---
layout: blogpost
title: "Specialized Value Objects for entity identifiers"
permalink: blog/specialized-value-objects-for-entity-identifiers
date: 2021-10-31 09:20
tag: ["Hibernate", "ORM"]
---

You're probably already using Value Objects daily. Most likely, you've come across LocalDate, LocalTime, Instant, URL, Path, ... etc. These are provided by the language and are
very generic. Maybe you've written your own for something like an Email or PhoneNumber, but they're still relatively generic and can have a lot of use-cases across your codebase,
even if they're specialized for your problem domain.

But, you can also have specialized VOs for single-place use, and there is nothing wrong with that. Thanks to the specialization, you can be extremely strict about the values you'll
allow, and you're getting a level of type safety you would not get with plain strings or even generic VOs.

<!--more-->
## Using Value Objects for entity identifiers

My favourite specialized VO is entity identifier. Yes, that's right - we have a specialized class for every entity, just for its ID. Bear with me - I have good reasons. But first,
let's see how you'd use it.

~~~java
@Entity
public class User
{

    @javax.persistence.Id
    @Column
    @NotNull
    @Type(type = ObjectUuidType.NAME)
    private Id id;

    public User()
    {
        this.id = Id.random();
    }

    public Id getId()
    {
        return id;
    }

    public static final class Id extends ObjectUuid<Id>
    {

        private Id(final UUID inner)
        {
            super(inner);
        }

        public static Id random()
        {
            return ObjectUuid.randomUUID(Id::new);
        }

        public static Id fromString(final String name)
        {
            return ObjectUuid.fromString(Id::new, name);
        }

        public static Id fromUuid(final UUID uuid)
        {
            return ObjectUuid.fromUuid(Id::new, uuid);
        }

    }

}
~~~

The `ObjectUuid` is the type's base class that wraps Java's native UUID and defines static helpers to lower the amount of boilerplate necessary for the static factory functions.
Sadly, Java doesn't have [the concept of late static binding known from PHP](https://www.php.net/manual/en/language.oop5.late-static-bindings.php), which would allow me to cut down
on the boilerplate even more. But it doesn't contain any logic; it exists only to have types handled correctly, so it's tolerable.

If you look at the constructor, you can see it's similar to the usage of native `UUID` where you'd assign `UUID.randomUUID()` to `this.id`.

Serializing and hydrating the `Id` from the database is handled by `ObjectUuidType`, and it works nicely even in HQL or criteria Hibernate queries.

Let's look outside of the entity. Here you're most likely to create the instance of `Id` in controllers.

~~~java
@GetMapping("/users/{userId}")
public ModelAndView getUser(
    @PathVariable("userId") @AssertUuid final String rawUserId
)
{
    User.Id userId = User.Id.fromString(rawUserId);
    User user = userFacade.getUser(userId);

    return new ModelAndView("user/detail")
        .addObject("user", user);
}
~~~

First, I'm validating the shape of the id with `@AssertUuid`. If the `userId` was not a correct UUID, the action would not execute and just return a 400 Bad Request. Then I convert
the value directly to `User.Id` using the `fromString` factory function. As with all good Value Objects, the factory function would throw an exception if given an invalid UUID. But
thanks to the validation annotation, we can rest easy without handling the exception explicitly.

Notice that I'm referencing the class with `User.Id`. This has two reasons. First is that Java doesn't have import aliases - I cannot write `import User.Id as UserId;`, but I could
name the class `UserId` and then I would be able to import it with `import User.UserId;`. But more importantly - I think it's nicely readable, and it looks much better than naming
the `Id` class `UserId` or even declaring it next to the `User` and not as an inner class.

## Life without Value Objects for entity identifiers

Let's imagine you're using integer identifiers for entities, and you mix up the ID's.

~~~java
int userId = Integer.parseInt(request.getQuery("articleId"));
int articleId = Integer.parseInt(request.getQuery("userId"));
~~~

Here you can see the problem at first glance, but it's not always so obvious, and a bit of carelessness or slightly more complex code can easily lead to mixing the values just like
this.

A more real-life example could be that you're using the IDs for indexing a hash map.

~~~java
// first is User id and the second is Article id
Map<Integer, Map<Integer, Something>> indexByIds = new HashMap<>();
~~~

Here the helpful variable name next to the type definition is missing, and it's really easy to mix up the two ID's.

Or imagine a repository method that accepts the two id's.

~~~java
public User getArticleWrittenByUser(
    final int userId,
    final int articleId
)
{
    // impl
}
~~~

Nothing prevents you from mixing these two arguments; both variants are perfectly valid and will compile without errors.

~~~java
articleRepository.getArticleWrittenByUser(userId, articleId);
articleRepository.getArticleWrittenByUser(articleId, userId);
~~~

What's even more worrisome is that if you're using integer ID's, this code can even appear to work! You can have an article with ID 5 written by a user with ID 3, and an article
with ID 3 written by a user with ID 5. If you happen to test the code on such an example, you won't realize it's broken until you deploy it to production. This problem can be
partially mitigated by using UUID's instead of integers for entity identifiers because you'd have to be extremely unlucky to have such a combination of UUID's that would appear to
work; with UUID's the code would simply return nothing and hopefully fail with some kind of runtime error a bit later.

## Life with Value Objects for entity identifiers

After converting the first example to using specialized VO's for ID's we can see that this approach is not a silver bullet, and getting rid of code reviews would be a bad idea.

~~~java
User.Id userId = User.Id.fromString(request.getQuery("articleId"));
Article.Id articleId = Article.Id.fromString(request.getQuery("userId"));
~~~

But the second example is where it starts to make sense. You'd have to be really creative to be able to mix up the ids.

~~~java
Map<User.Id, Map<Article.Id, Something>> indexByIds = new HashMap<>();
~~~

The example with repository is similarly convincing.

~~~java
public User getArticleWrittenByUser(
    final User.Id userId,
    final Article.Id articleId
)
~~~

If you fill the variables with correct values, it will be impossible to mix them up.

~~~java
articleRepository.getArticleWrittenByUser(userId, articleId);
articleRepository.getArticleWrittenByUser(articleId, userId); // compile error
~~~

## Conclusion

As you can see, this technique can eradicate a wide range of errors. I'll probably release these two classes as a library to Maven Central, but I didn't want to taint the idea with
a specific implementation - this principle can be applied not only to Hibernate in Java but also to Doctrine in PHP and other languages and frameworks.
