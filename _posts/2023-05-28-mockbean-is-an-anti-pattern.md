---
layout: blogpost
title: "Spring's @MockBean is an anti-pattern"
permalink: blog/mockbean-is-an-anti-pattern
date: 2023-05-28 21:00
tag: ["Java", "Spring Boot", "Spring Framework", "Testing"]
---

I really like Spring Boot because it's super-powerful, but like with any other power tool, you have to be careful not to lose a finger or even a whole arm.

One such tool is Spring's [@MockBean](https://www.baeldung.com/java-spring-mockito-mock-mockbean#spring-boots-mockbean-annotation), which allows you to easily replace a service (bean) in Spring's application context.
This is really useful because having to think about all the places where a service is used and how to fully replace it in the context in order to mock it is a huge pain, and sometimes it can even be impossible.

But is it worth the price?

<!--more-->

## The problem

Spring has many mechanisms that allow you to configure the context in tests.
You can, for example, attach an extra configuration class or property file for a single test case which, again, is really powerful and simple to use, but all of these config overrides can affect the resulting context.
Each time a test is executed, Spring has to check its configuration, and then it has to make sure it can provide you with a correctly configured context.
Thankfully, Spring is smart, so instead of creating a new context for every single test, it can take all the config inputs and create a caching key to reuse the contexts between tests.

The problem with `@MockBean` is that you're affecting the config inputs determining which tests can reuse the context.

```java
public class UserTest1 { // simplified

    @MockBean
    UserRepository mockUserRepository;
```

```java
public class UserTest2 { // simplified

    @MockBean
    UserRepository mockUserRepository;

    @MockBean
    UserService mockUserService;
```

```java
public class UserTest3 { // simplified

    @MockBean
    UserService mockUserService;
```

Each of these samples has a unique set of mocked beans, so Spring cannot reuse the context and must create a brand new one for each test.

If you [search for `MockBean` in Spring Boot's GitHub repository](https://github.com/spring-projects/spring-boot/issues?q=MockBean) it turns out this surprised many people.

## Does it actually matter?

The first problem which will slowly creep up on you is time.
Initializing the application context and all the necessary services for a test takes time. And for a big application, it can take a lot of time.
You might not notice if your test suite runs minutes or longer anyway (neither did we at first), but slow tests mean nobody wants to maintain them.

One of the more obscure problems that hit us out of nowhere is that the default maximum number of cached contexts is 32, which means there can be **up to 32 unclosed contexts**.
And if your application uses a database (like most do), let's say a PostgreSQL, then you might find that PostgreSQL doesn't like having too many connections open simultaneously.
It's rarely a problem in production where the database has a lot of RAM, meaning it can hold many connections.
Still, tests are usually configured with a tiny PostgreSQL instance running in a small docker container, so if you create too many connections, your tests might start failing because they cannot create more connections.
This limit is very easy to hit when you open 32 contexts where each one holds a connection pool with 10+ connections.

## How can we fix it?

My favourite solution can be found [in one of Baeldung's articles](https://www.baeldung.com/spring-tests#2-the-problems-withmockbean) - just avoid mocks.
Mocks are objectively overused, and in many cases, you can write the test without them.

Another alternative is to attempt to replace the service in contexts' beans surgically and then put it back.
That sounds good at first, but you can end up with a partially reverted state if you make a mistake, and completely unrelated tests might start failing.
Some people attempted to make the "revert" more reliable [by creating a library for it](https://github.com/antoinemeyer/mock-in-bean/).
It works, but I think it's a very fragile technique and would personally avoid it.

The last popular solution known to me is to declare a shared mock/spy service for the entire lifetime of tests, e.g. like this:

```java
@Configration
public class MockConfig {

    @Primary
    @Bean
    public ExternalService externalService() {
        return Mockito.mock(ExternalService.class);
    }
}
```

This has the advantage that if you replace a service that might do some external HTTP calls, you cannot call it in your tests by accident.

The disadvantage is that if you need to mock a service that is otherwise a critical part of your application, the application stops working.
You also have to make sure that you reset the mock between tests. Otherwise, your tests might affect each other, which can be very hard to debug.
That's a lot of things you need to get right which might bite you.

## A better alternative

_Notice: you're reading an updated version of this article, [the previous version was achieving almost identical behaviour but with a custom solution](https://github.com/fprochazka/filip-prochazka.com/blob/4ae0a66bcca749cb88c1843e0a84b1bc0c85b86c/_posts/2023-05-28-mockbean-is-an-anti-pattern.md)._
_Thank you [random commenter on reddit](https://www.reddit.com/r/java/comments/13ub933/comment/jm1p1ms/) for pointing out that there is a native Spring mechanism for exactly this, and I can drop my custom workaround._

I've prepared [a working project](https://github.com/fprochazka/spring-mock-wrapped-bean-demo) where you can see demonstrated both the problem and the solution. So how do we solve this?

1. we replace all `@MockBean` with plain old `@Autowired`
2. we define `@SpyBean` entry for each service on a **central and shared** location, which may look like this:

```java
@TestConfiguration
public class TestOverridesConfiguration {

    @SpyBean
    private ExternalService externalService;

}
```

This makes Spring replace the bean definition in its context with a spy mock, and if you don't define any mocking behaviour within the tests,
it will default to calling the real methods, making your application work as if nothing was mocked.
It also takes care of cleanup between tests, so that they don't affect each other.
This enables us to mock even critical services that the application requires to work correctly without having to configure the mocking behaviour in every test.

I want to stress that simply replacing `@MockBean` with `@SpyBean` fixes nothing, the critical part is putting it into a configuration that is loaded in all tests, so that Spring doesn't create more than one context.

How do you use it in a test? Similarly to how you'd use `@MockBean` - you autowire the service, configure the behaviour in your test and let the magic happen.

```java
class FooServiceTest extends BaseTestCase {

    @Autowired
    ExternalService externalService;

    @Autowired
    FooService fooService;

    @Test
    public void computation() {
        Mockito.doReturn(42)
            .when(externalService)
            .fetchCounterExternally();

        int actual = fooService.computation(); // this calls ExternalService internally

        assertThat(actual).isEqualTo(94);

        Mockito.verify(externalService, Mockito.times(1))
            .fetchCounterExternally();
    }

}
```

IMHO, the only advantage of `@MockBean` over this approach is that you can clearly see which services are meant to be mocked in the test.
But in practice, it's not a problem because you don't have to care - just start writing the mocking configuration,
and if it turns out that the service doesn't have a `@SpyBean` defined, Mockito will yell at you that the given object is not a mock, so you just add it to the config and your test will pass.

This whole approach is battle-tested and works really well for us in [ShipMonk](https://rnd.shipmonk.com/).
As of writing this article, we have 25+ services (and growing) mocked this way in a not-so-small project, and everything works flawlessly.

As a cherry on top, you could write a custom [ErrorProne rule](https://github.com/google/error-prone) that will fail the build
if somebody uses the forbidden `@MockBean` by accident (which we did, but more on that some other time).

## Conclusion

This article is mostly about avoiding `@MockBean`, but you can just as easily introduce the same problem by using any other per-test config override.
I'm always trying to completely avoid anything that would cause multiple contexts to be created.
We've made it a rule to have a single [BaseTestCase](https://github.com/fprochazka/spring-mock-wrapped-bean-demo/blob/master/example-fix-spybean/src/test/java/com/fprochazka/mockbean/testing/BaseTestCase.java),
that contains all the test-related configs and overrides, and none of the tests defines their own.
The only situation where I couldn't avoid overriding configs in individual tests was when I was writing a library-like functionality with parametrized configuration classes,
but that can be easily extracted into a separate Maven module, so it doesn't have to affect your application.

The Spring developers are [trying to tackle this problem systematically](https://github.com/spring-projects/spring-boot/issues/34768), but until they do, `@MockBean` is an anti-pattern in my book.

How do _you_ solve this problem? Do you have an idea to improve this further? Let me know; thanks!
