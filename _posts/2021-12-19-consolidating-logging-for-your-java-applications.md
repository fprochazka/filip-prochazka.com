---
layout: blogpost
title: "Consolidating logging for your Java applications"
permalink: blog/consolidating-logging-for-your-java-applications
date: 2021-12-19 21:00
tag: ["Java", "Logging", "SFL4J", "Log4j", "Logback", "Maven"]
---

On the surface, logging in Java may appear to be standardized, but there are a [few competing standards](https://xkcd.com/927/), which creates problems that we need to address.
This article will look into replacing all the unwanted logging libraries with just one.

If you're looking into how to completely and safely remove [Log4j](https://logging.apache.org/log4j/) from your projects, this article shows exactly that. I'm not advocating you
should do that since you might just be trading a set of known problems for some unknown ones; I'm just saying this is how you can do it if you want to.

<!--more-->

## Basics of logging in Java

There are many logging libraries and abstractions in Java, just to name a few:

* [JDK logging](https://docs.oracle.com/javase/8/docs/api/java/util/logging/Logger.html)
* [Logback](http://logback.qos.ch/)
* [Log4j](https://logging.apache.org/log4j/)
* [JBoss Logging](https://github.com/orgs/jboss-logging/repositories)
* [SLF4J](http://www.slf4j.org/)
* [Apache Commons-logging](https://commons.apache.org/proper/commons-logging/)
* ... and probably a lot more

In my opinion, all libraries and applications should use the [SLF4J](http://www.slf4j.org/manual.html) for logging. It's a good enough standard, and it provides interfaces that abstract logging library implementations. But
real-world is not as simple, and each library ends up using something else, and some even directly depend on a specific logging implementation. So like everyone else, you also have to pick a library for logging.

In my case, when I've started working with Spring Boot, the default logging library was (and as of writing this article still is) [Logback](http://logback.qos.ch/). Spring Boot allows you to switch to other logging libraries
easily, but I didn't have a reason to do that, so I've stuck with Logback ever since.

But the problem is that not every dependency you'll install will agree with you, and having multiple logging frameworks installed in your applications is an integration nightmare.

## Selectively excluding dependencies

If you start with a basic Spring Boot project, you'll probably have only Logback installed. But what happens if you add a dependency
on [`com.amazonaws:aws-java-sdk-ssm`](https://mvnrepository.com/artifact/com.amazonaws/aws-java-sdk-ssm/1.12.125)?

~~~xml
<dependency>
    <artifactId>aws-java-sdk-ssm</artifactId>
    <groupId>com.amazonaws</groupId>
    <version>${aws-java-sdk.version}</version>
</dependency>
~~~

You'll transitively get also [`com.amazonaws:aws-java-sdk-core`](https://mvnrepository.com/artifact/com.amazonaws/aws-java-sdk-core/1.12.125) which depends on `commons-logging:commons-logging` and suddenly, you have two logging
frameworks installed, and we want to avoid that.

Let's start fixing it by inspecting the entire dependency tree of your project using:

~~~bash
mvn dependency:tree
~~~

which outputs a lot of ASCII-art, but we'll only look at this part:

~~~
[INFO] +- com.amazonaws:aws-java-sdk-ssm:jar:1.12.125:compile
[INFO] |  +- com.amazonaws:aws-java-sdk-core:jar:1.12.125:compile
[INFO] |  |  +- commons-logging:commons-logging:jar:1.1.3:compile
~~~

Maven has a special configuration section [`<dependencyManagement>`](https://maven.apache.org/guides/introduction/introduction-to-dependency-mechanism.html) for overriding (even transient) dependencies. Let's use it to fix our
problem. We'll also include the [BOM](https://maven.apache.org/guides/introduction/introduction-to-dependency-mechanism.html#bill-of-materials-bom-poms) for AWS Java SDK to lock down the versions that Maven installs.

~~~xml
<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>com.amazonaws</groupId>
            <artifactId>aws-java-sdk-bom</artifactId>
            <version>${aws-java-sdk.version}</version>
            <type>pom</type>
            <scope>import</scope>
        </dependency>

        <dependency>
            <artifactId>aws-java-sdk-core</artifactId>
            <groupId>com.amazonaws</groupId>
            <version>${aws-java-sdk.version}</version>
            <exclusions>
                <exclusion>
                    <groupId>commons-logging</groupId>
                    <artifactId>commons-logging</artifactId>
                </exclusion>
            </exclusions>
        </dependency>
    </dependencies>
</dependencyManagement>
~~~

As a nice side-effect, you'll no longer need to specify the version of the dependency in `<dependencies>` because it's now defined in `<dependencyManagement>`. This might look like a lot more work, but you'll appreciate it in any
bigger projects.

~~~xml
<dependencies>
    <dependency>
        <artifactId>aws-java-sdk-ssm</artifactId>
        <groupId>com.amazonaws</groupId>
    </dependency>
</dependencies>
~~~

If you now run the `mvn dependency:tree` again, you'll see that the `commons-logging` is not there anymore. Nice!

## Providing a replacement

We've got rid of the unwanted library, but now we have another problem - the `aws-java-sdk-ssm` library won't work because we've basically removed the needed classes from the project. Now is the time to talk about how JVM is
loading classes.

There are multiple ways this can be configured, but what happens, in essence, is that when you install a dependency, Maven downloads the JAR and instructs your application that it needs to add the JAR onto its classpath when it's
starting. But neither Maven nor JVM cares about what actually is in the JAR files. Nothing is preventing you from publishing a class in `org.apache.commons.logging` package that you'll release
to [Maven Central](https://search.maven.org/) as an `my.company:override-logging` artefact.

And this is how the library authors allow you to remove the dependency that the library needs without breaking it. They provide a binary-compatible alternative implementation that uses their library under the hood. Looking at your
dependency tree, you'll see that Spring already depends on a few of those overriding implementations out-of-the-box. For example `org.apache.logging.log4j:log4j-to-slf4j`, `org.slf4j:jul-to-slf4j`
and `org.springframework:spring-jcl`.

## Preventing unnoticed installations of an unwanted library

We've cleaned the dependency tree, but what if we want to upgrade or install a new library? With any dependency change, you're risking that the unwanted library will be newly imported. This can be prevented with the help
of [`maven-enforcer-plugin`](https://maven.apache.org/enforcer/maven-enforcer-plugin/enforce-mojo.html), which can scan your dependencies and kill the build if it finds a dependency you never wanted to install.

~~~xml
<plugin>
    <groupId>org.apache.maven.plugins</groupId>
    <artifactId>maven-enforcer-plugin</artifactId>
    <version>${maven-enforcer-plugin.version}</version>
    <executions>
        <execution>
            <id>enforce</id>
            <configuration>
                <rules>
                    <banDuplicatePomDependencyVersions/>
                    <bannedDependencies>
                        <excludes>
                            <!-- slf4j should be used instead -->
                            <exclude>commons-logging:commons-logging</exclude>
                            <exclude>commons-logging:commons-logging-api</exclude>
                            <exclude>org.apache.logging.log4j:log4j-core</exclude>
                            <exclude>org.slf4j:slf4j-simple</exclude>
                        </excludes>
                    </bannedDependencies>
                    <dependencyConvergence/>
                </rules>
            </configuration>
            <goals>
                <goal>enforce</goal>
            </goals>
        </execution>
    </executions>
</plugin>
~~~

Now, if you'll remove the exclusion we've defined previously, you'll see a build error.

~~~
[INFO] --- maven-enforcer-plugin:3.0.0:enforce (enforce) @ demo ---
[WARNING] Rule 1: org.apache.maven.plugins.enforcer.BannedDependencies failed with message:
Found Banned Dependency: commons-logging:commons-logging:jar:1.1.3
Use 'mvn dependency:tree' to locate the source of the banned dependencies.
~~~

It's not very verbose, but with a bit of help from our new buddy `mvn dependency:tree`, you'll be able to easily fix it.

## Forcing usage of SFL4J in your project

You should now have a nice dependency tree with only the libraries you want. But even though the `org.apache.commons.logging.LogFactory` now calls SFL4J, you still probably don't want to accidentally use it instead
of `org.slf4j.LoggerFactory` - that would be pure chaos. We can fix even this by adding an extra dependency [`restrict-imports-enforcer-rule`](https://github.com/skuzzle/restrict-imports-enforcer-rule) to the enforcer plugin!

~~~xml
<build>
    <pluginManagement>
        <plugins>
            <plugin>
                <groupId>org.apache.maven.plugins</groupId>
                <artifactId>maven-enforcer-plugin</artifactId>
                <version>${maven-enforcer-plugin.version}</version>
                <dependencies>
                    <dependency>
                        <groupId>de.skuzzle.enforcer</groupId>
                        <artifactId>restrict-imports-enforcer-rule</artifactId>
                        <version>${enforcer-rule-restrict-imports.version}</version>
                    </dependency>
                </dependencies>
            </plugin>
        </plugins>
    </pluginManagement>
</build>
~~~

Next, we add a second execution to the enforcer plugin's `<executions>`.

~~~xml
<execution>
    <id>check-imports</id>
    <phase>process-sources</phase>
    <goals>
        <goal>enforce</goal>
    </goals>
    <configuration>
        <rules>
            <RestrictImports>
                <reason>Use SLF4j for logging</reason>
                <bannedImports>
                    <bannedImport>java.util.logging.**</bannedImport>
                    <bannedImport>org.apache.commons.logging.**</bannedImport>
                    <bannedImport>org.apache.logging.log4j.**</bannedImport>
                </bannedImports>
                <basePackages>
                    <basePackage>com.cogvio.**</basePackage>
                </basePackages>
                <includeTestCode>true</includeTestCode>
            </RestrictImports>
        </rules>
    </configuration>
</execution>
~~~

And now, if we accidentally use the wrong logging API, we'll get a helpful build error.

~~~
[INFO] --- maven-enforcer-plugin:3.0.0:enforce (check-imports) @ core-http ---
[WARNING] Rule 0: org.apache.maven.plugins.enforcer.RestrictImports failed with message:

Banned imports detected:

Reason: Use SLF4j for logging
    in file: com/cogvio/user/UserFacade.java
        org.apache.commons.logging.Log (Line: 6, Matched by: org.apache.commons.logging.**)
        org.apache.commons.logging.LogFactory (Line: 7, Matched by: org.apache.commons.logging.**)
~~~

## Conclusion

I have no strong opinion about what logging library implementation you should actually use. I just happen to use Logback. But I believe that without a proper enforcement mechanism, you can end up installing something you don't
want to.

You might think you're using Logback like me, but some dependency might pull an old and vulnerable version of Log4j into your project, and you might not even find out until someone hacks your servers. The vulnerable
dependency [might be 5 layers deep, and it's going to take some time to fix, test, and release all those libraries](https://security.googleblog.com/2021/12/understanding-impact-of-apache-log4j.html?m=1). It's simply not possible
to fix everything over a weekend.

But with the combination of using `<dependencyManagement>`, which can lock even transient dependencies and properly configured enforcer rules, you can save yourself a lot of surprises.

