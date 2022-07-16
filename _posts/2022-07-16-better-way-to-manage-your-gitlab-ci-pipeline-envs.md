---
layout: blogpost
title: "Better way to manage your Gitlab CI Pipeline ENVs"
permalink: blog/better-way-to-manage-your-gitlab-ci-pipeline-envs
date: 2022-07-16 12:30
tag: ["Gitlab", "CI/CD", "Terraform"]
---

I often find myself copying AWS access keys from IAM Users to Gitlab's ENV if I'm automating a deployment for a project or something else. Most of the time, it's a set-and-forget process, but sometimes you have to go back and investigate, and sometimes you wonder - where did these come from? What are their actual permissions? Who else is using them? Where can I change their permissions? How can I completely delete this user?

<!--more-->

## How to read the value of a Terraform secret?

Before I show you the solution, let's first add another problem to the equation. Imagine you want to manage everything with Terraform, including the AWS access keys, which means you'd write something like this:

```terraform
resource "aws_iam_user" "pm_ci_deploy" {
  name = "pm-ci-deploy"
}

resource "aws_iam_access_key" "pm_ci_deploy_access_key" {
  user = aws_iam_user.pm_ci_deploy.id
}
```

But now you have a problem because Terraform won't print the secrets out to the console.

```sh
$ terraform state show aws_iam_access_key.pm_ci_deploy_access_key

# aws_iam_access_key.pm_ci_deploy_access_key:
resource "aws_iam_access_key" "pm_ci_deploy_access_key" {
    create_date          = "2022-07-15T15:26:18Z"
    id                   = "AKIAXYZXYZXYZXYZXYZX"
    secret               = (sensitive value)
    ses_smtp_password_v4 = (sensitive value)
    status               = "Active"
    user                 = "pm-ci-deploy"
}
```

There is currently no *good* solution that I know of, only various workarounds. My workaround has been to download and inspect the raw Terraform state, but then you have the file in plaintext lying around on your hard disk, which is less than ideal.

However, a solution exists that allows you to transfer the credentials to their target destination without you having to copy&paste them in plaintext! 

## Terraform Gitlab provider

Let's see how easy it is to [install the Gitlab provider](https://registry.terraform.io/providers/gitlabhq/gitlab/latest/docs) and manage the EVNs with it.

Once you have the provider installed, you have to configure it with a token  (and optionally a base URL of your on-premise instance) - I've opted to create a single token for my entire Gitlab group so that I can manage everything easily.

Then there is the question of how to configure the token. You could paste it into the provider block in plaintext, but that's not very secure since you'll want to commit that code to a git repository. Alternatively, the provider states that it can read the `GITLAB_TOKEN` environment variable, which is better, but might not be very DX friendly on your local machine. I've decided to store it in a [SOPS secrets file](https://registry.terraform.io/providers/carlpett/sops/latest/docs), which enables me to have a consistent experience when working with the project.

```terraform
provider "gitlab" {
  base_url = "https://gitlab.com/api/v4/"
  token = data.sops_file.secrets.data["gitlab.maintainer-token"]
}
```

## Usage

I don't want to manage the entire Gitlab instance with Terraform (at least for now), just the ENV variables in individual projects - I'll be using a data provider to look up the project and then create resources for the individual ENVs.

```terraform
data "gitlab_project" "cogvio_pm_backend" {
  id = "cogvio/pm/backend"
}

resource "gitlab_project_variable" "AWS_ACCESS_KEY_ID_4_cogvio_pm_backend" {
  project = data.gitlab_project.cogvio_pm_backend.id
  key = "AWS_ACCESS_KEY_ID"
  value = aws_iam_access_key.pm_ci_deploy_access_key.id
  protected = true
  masked = true
}

resource "gitlab_project_variable" "AWS_SECRET_ACCESS_KEY_4_cogvio_pm_backend" {
  project = data.gitlab_project.cogvio_pm_backend.id
  key = "AWS_SECRET_ACCESS_KEY"
  value = aws_iam_access_key.pm_ci_deploy_access_key.secret
  protected = true
  masked = true
}
```

And with this snippet, you're ready to start managing the credentials securely.
