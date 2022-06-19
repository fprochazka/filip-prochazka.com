---
layout: blogpost
title: "Cost-effective Gitlab runners"
permalink: blog/cost-effective-gitlab-runners
date: 2022-06-19 20:45
tag: ["Gitlab", "CI/CD", "Performance"]
---

In modern software development, it's unimaginable that you don't have some CI/CD build server hooked into your version control, so I probably don't have to introduce [Gitlab CI/CD](https://docs.gitlab.com/ee/ci/).

But how do you do that _effectively_, to save money and time?

<!--more-->

## The story

When COGVIO started in 2017, we had an on-premise Gitlab instance and needed Gitlab runners. Starting more servers for 2 devs that would run 24/7 and do nothing most of the time seemed wasteful when I had an overpowered desktop that I was utilizing at barely 30%. I've figured I could run at least 3 Gitlab runners and still be able to work normally without noticing any slowdown.

As you can probably guess, this worked fine for some time, but then the team and the app grew, and the build times grew with it. And if you have a fixed number of runners and your build takes more than a few minutes, and your devs push a lot of commits that all start CI pipelines, you soon find yourself waiting many hours for your jobs to build. Even worse is when you really need to deploy to production, and the devs have managed to clog up the runners for the rest of the day at 10 AM.

We've decided to solve this with autoscaling Gitlab runners. As the name suggests, Gitlab runners can start virtual servers for running your jobs on them and then turn them off when they're no longer needed. Obviously, autoscaling is much better than running many machines 24/7 - it saves cost when there is nothing to build, but more importantly, it saves the developers' time, who don't need to wait for their turn for the build server.

## The main issue with `docker+machine` Gitlab runner

When I was configuring this, [the `docker+machine` runner](https://docs.gitlab.com/runner/executors/docker_machine.html) seemed like the best choice - it was cost-effective and relatively simple to set up. You just need one tiny instance where you'll install the Gitlab runner, which will orchestrate the other instances. We've also configured the runner to [use EC2 spot instances](https://docs.gitlab.com/runner/configuration/runner_autoscale_aws/#cutting-down-costs-with-amazon-ec2-spot-instances) to save even more money.

The autoscaling Gitlab runner `docker+machine` utilizes [docker-machine](https://github.com/docker/machine), which can use several cloud providers to start a virtual machine, install Docker on it and then allows you to run containers on it. Sadly, the docker-machine was deprecated by Docker, but Gitlab is [maintaining a fork](https://gitlab.com/gitlab-org/ci-cd/docker-machine), which you can use until they find a better solution.

The problem with EC2 spots is that the API you call to create them allows you to specify only a single AZ. In the following months, we've managed to run into situations where the instance types we've been using were completely depleted in that AZ, and our jobs weren't starting anymore. Each time, I've managed to fix the problem by changing the configured AZ, but that was only a temporary fix.

The real solution is to use EC2 SpotFleet - a more advanced version of EC2 Spot instances. The problem is that docker-machine doesn't support SpotFleet, and since it's deprecated, there is little hope someone will implement it. So I've decided to do it myself - [I have forked the repo and added the support](https://gitlab.com/fprochazka/docker-machine/-/commits/fp-spot-fleet-support). It worked beautifully, and our problems with unavailable spot instance types disappeared. Let's also don't forget to thank [Petr Soukup](https://twitter.com/petrsoukup/) who recently [helped to rebase my work onto the latest version of Gitlab's fork](https://github.com/soukicz/docker-machine/commits/fp-allow-multiple-availability-zones-rebased).

Right now, looking at the article [Which is the best Spot request method to use?](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/spot-best-practices.html#which-spot-request-method-to-use), it seems that more work is needed again, as the method `RequestSpotFleet` is now also deprecated. But knowing AWS, this will probably work for a few more years.

## Different instance sizes

As you'll see later in the article, it's very beneficial to right-size EC2 instances for the jobs. The T3 instances are cheap and even cheaper with the SpotFleet, but they are not very fast, and you really don't want to compile big Java projects on them. But you also don't want to build every job on big C5 instances.

You can solve this by simply registering the runner multiple times with different configurations. Give each configuration a tag `small`, `medium`, `large`, etc., and when you add the same tag to a job, [the corresponding runner will execute it](https://docs.gitlab.com/ee/ci/runners/configure_runners.html#use-tags-to-control-which-jobs-a-runner-can-run). You don't even have to create new instances, since the runner is not actually executing the jobs, you can register it multiple times on the same tiny orchestration machine.

## What you'll get

I've already mentioned it, but this is so crucial that it must be repeated - you get **autoscaling**. The ability to start as many runners as the devs request in near-real-time is a must. If you have a lot of devs, you'll like the "working hours" feature that allows you to start a few idle servers before anyone needs them. Starting the server takes a minute or two, which doesn't seem like much, but when you structure your pipelines to be heavily parallelized with many stages, the wait time adds up quickly. It's also good to configure `IdleTime`, which instructs the runner to keep the server around for some time even after it's done running the job - this helps to reduce wasteful waiting for re-creating a recently deleted server.

You obviously save a lot of money by not wasting the time of your devs, but that is not that easy to calculate. But with a few API calls, you can get billing data for your Gitlab runners and easily see how much money it has cost you and how much you've saved.

## Hard data

The cost graph shows the cost of the compute alone, and the "on-demand" line highlights the relative savings of using SpotFleet. Each instance also costs for storage and network, but I have to admit I didn't set up resource tagging properly, so getting the correct data would be hard, but it's safe to guess the cost of that would be only a few more dollars per month. And there is also the additional cost of the `t3.micro` instance for orchestrating the jobs, which costs ~$9/month.

As you can see from the graph, we've been optimizing the build time of pipelines by using larger EC2 instances around February, which obviously costs more.

![build cost](/content/cost-effective-gitlab-runners-build-cost.svg)

In the following graph, the "CI Jobs" line is from Gitlab's database (one of the perks of an on-premise installation is that you get to query your own instance with SQL easily), and the columns are from billing data. As you can see, there are months where the instances were idling over 5/6 of the time - I was quite surprised, so I had to re-check it several times. This suggests that there is much room for optimizing the `IdleTime` - most likely because the dev team is not that large, and the started instances don't have a chance to be reused that often. This wasn't a big deal as long as the pipelines burned around ~$20/month, but now it might be worth optimizing.

![build time](/content/cost-effective-gitlab-runners-build-time.svg)

## Gotchas of `docker+machine` + `Dind`

The individual CI jobs are basically isolated as much as possible - this gives you extra safety, but it also creates performance challenges. With a bit of tinkering, you have the potential to allow some minor local reuse of docker images and build cache, but since the instances are disposed of frequently, there is not much room for the reuse to happen anyway, which increases the build time of the CI jobs. If you have long builds, you might not notice it, but with quick jobs, this extra time can take up most of the job's run time and become quite annoying.

But even if you don't mind the bit of extra build time, you will eventually notice the outrageous network charges from AWS. Luckily, our Gitlab's EC2 instance is in the same VPC as our Gitlab runners, which means that any API calls to Gitlab (docker registry, etc.) can be redirected inside the VPC, saving some money.

```toml
  [runners.docker]
    extra_hosts = [
      "gitlab.company.com:172.31.33.5",
      "docker.company.com:172.31.33.5"
    ]
```

It is also a good idea to enable [Gateway endpoints for Amazon S3](https://docs.aws.amazon.com/vpc/latest/privatelink/vpc-endpoints-s3.html), saving you even more money.

Then there is also the question of the instance cold-start itself. If you want to have a lot of tiny jobs that do a few AWS CLI calls and not much else, it might be worth having a few permanent instances which would handle these micro-jobs instead of waiting for several `t3.micro` instances to boot up.

## Conclusion

In conclusion, our usage wasn't the most effective, but we got the much-needed autoscaling, and the wasted money was negligible. I think the data nicely illustrates what can be achieved.
