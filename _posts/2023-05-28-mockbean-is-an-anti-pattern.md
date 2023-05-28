---
layout: blogpost
title: "Spring's @MockBean is an anti-pattern"
permalink: blog/mockbean-is-an-anti-pattern
date: 2023-05-28 21:00
tag: ["Java", "Spring Boot", "Spring Framework", "Testing"]
---

I really like Spring Boot because it's super-powerful, but like with any other power tool, you have to be careful not to lose a finger or even a whole arm.

One such tool is Spring's [@MockBean](https://www.baeldung.com/java-spring-mockito-mock-mockbean), which allows you to easily replace a service (bean) in Spring's application context.
This is really useful because having to think about all the places where a service is used and how to fully replace it in the context in order to mock it is a huge pain, and sometimes it can even be impossible.

But is it worth the price?

<!--more-->

## The problem

Spring has many mechanisms that allow you to configure the context in tests.
You can, for example, attach an extra configuration class or property file for a single test case which, again, is really powerful and simple to use, but all of these config overrides can affect the resulting context.
Each time a test is executed, Spring has to check its configuration, and then it has to make sure it can provide you with a correctly configured context.
Thankfully, Spring is smart, so instead of creating a new context for every single test, it can take all the config inputs and create a caching key to reuse the contexts between tests.

The problem with `@MockBean` is that you're affecting the config inputs determining which tests the context can be reused.

```java
@RunWith(SpringRunner.class)
public class UserTest1 {

    @MockBean
    UserRepository mockUserRepository;
```

```java
@RunWith(SpringRunner.class)
public class UserTest2 {

    @MockBean
    UserRepository mockUserRepository;

    @MockBean
    UserService mockUserService;
```

```java
@RunWith(SpringRunner.class)
public class UserTest3 {

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

I've prepared [a working project](https://github.com/fprochazka/spring-mock-wrapped-bean-demo) where you can see the following demonstrated.

My solution is based on replacing the service with a mock for the entire runtime of tests, but with a slight improvement:

```java
@Primary
@Bean
public ExternalService externalServiceMock(final ExternalService real) {
    return Mockito.mock(ExternalService.class, AdditionalAnswers.delegatesTo(real));
}
```

This forces Spring to create a real instance of my original service and autowire it here for me to create a [delegating mock](https://site.mockito.org/javadoc/current/org/mockito/AdditionalAnswers.html#delegatesTo(java.lang.Object)).
This service is then marked as primary, so autowiring uses the mock instead of the real instance.
This enables me to mock even critical services that the application requires to work correctly without having to configure the mocking behaviour in every test.

Sadly, if you try to run this snippet, you might hit a wall because marking a bean as a primary and wanting Spring to autowire the non-primary (real) one doesn't work that well.
The easiest fix is to use `@Qualifier` to tell Spring the bean name of the real `ExternalService` so it autowires it correctly instead of failing on circular dependency.
But that's not very fun, so alternatively you can use the [custom BeanProcessor](https://github.com/fprochazka/spring-mock-wrapped-bean-demo/blob/master/src/test/java/com/fprochazka/mockwrappedbean/testing/mocking/MockWrappedBeanResetBeanProcessor.java), which uses a custom qualifier to modify how the autowiring works for these mocked services.
Now if I write `@MockWrappedBean` instead of `@Primary`, the problem is gone.

```java
@MockWrappedBean
@Bean
public ExternalService externalServiceMock(final ExternalService real) {
    return Mockito.mock(ExternalService.class, AdditionalAnswers.delegatesTo(real));
}
```

But what about the tests' pollution? If I forget to reset the mocks, other tests might start failing.
The demo project contains a solution even for this - the [custom TestExecutionListener](https://github.com/fprochazka/spring-mock-wrapped-bean-demo/blob/master/src/test/java/com/fprochazka/mockwrappedbean/testing/mocking/MockWrappedBeanResetTestExecutionListener.java)
asks Spring to list all beans that are mocked this way and then resets the beans before/after every test, and now there is no way for you to forget to reset the mocks.
Resetting the mocks around every test (when you're not configuring any mock behaviour) is a bit wasteful but still a few orders of magnitude faster than creating more contexts.

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

You don't have to think about resetting it; you don't need to do anything special if you don't need to mock it.

IMHO, the only advantage of `@MockBean` over this approach is that you can clearly see which services are meant to be mocked in the test.
But in practice, it's not a problem because you don't have to care - just start writing the mocking configuration, and if it turns out that the service doesn't have a `@MockWrappedBean` defined, Mockito will yell at you that the given object is not a mock, so you just add it to the config and your test will pass.

This whole approach is battle-tested and works really well for us in [ShipMonk](https://rnd.shipmonk.com/).
As of writing this article, we have 25+ services (and growing) mocked this way in a not-so-small project, and everything works flawlessly.

As a cherry on top, you could write a custom [ErrorProne rule](https://github.com/google/error-prone) that will fail the build
if somebody uses the forbidden `@MockBean` by accident (which we did, but more on that some other time).

## Conclusion

This article is mostly about avoiding `@MockBean`, but you can just as easily introduce the same problem by using any other per-test config override.
I'm always trying to completely avoid anything that would cause multiple contexts to be created.
We've made it a rule to have a single [BaseTestCase](https://github.com/fprochazka/spring-mock-wrapped-bean-demo/blob/master/src/test/java/com/fprochazka/mockwrappedbean/testing/BaseTestCase.java), that contains all the test-related configs and overrides, and none of the tests defines their own.
The only situation where I couldn't avoid overriding configs in individual tests was when I was writing a library-like functionality with parametrized configuration classes, but that can be easily extracted into a separate Maven module, so it doesn't have to affect your application.

How do you tackle this problem? Do you have an idea to improve this further? Let me know; thanks!
