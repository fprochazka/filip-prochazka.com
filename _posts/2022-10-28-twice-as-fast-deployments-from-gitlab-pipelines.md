---
layout: blogpost
title: "Twice as fast deployments from GitLab pipelines"
permalink: blog/twice-as-fast-deployments-from-gitlab-pipelines
date: 2022-10-28 16:30
tag: ["Gitlab", "CI/CD", "Performance"]
---

All CI pipelines would finish in a few minutes in an ideal world, but sadly we don't live in a perfect world. Sometimes your pipelines take 15 minutes or more, and reducing that time would be pretty hard. That alone can be rather unpleasant, but the real problem begins if you're trying to ensure that you verify all your commits correctly. First, you have to wait for the CI to pass on a **rebased** merge request, then you merge the merge request, then you wait for the CI to pass on the merge commit in main, and only then a conditional job that runs only on main would deploy the application.

If you sum up all that waiting, you'll realize that a pipeline that takes 15 minutes means a full CI cycle of a bugfix will take you at least 30 minutes to deploy - potentially 30 minutes of downtime on production.

In this article, I'll show you one of the possible optimizations you can do to cut that time **in half** without losing any safety. A significant benefit of this approach is that you can implement it without re-architecting your workflow and CI from the ground up because it's only an incremental improvement.

<!--more-->

## Typical workflow

First, let's illustrate the typical workflow:

* You have the main branch, which is the source of truth and is used for deployments
* When you want to make a change, you create a new branch, commit your changes there and submit a merge request
* After a round or two of code reviews, you have the merge request ready to be merged
* After the merge, the CI runs again on the main to produce the necessary artefacts for deployment, and a deploy job is triggered automatically.

IMHO this is what most teams are doing in some form, but there are a few head-scratchers to this workflow that we should answer.

## Why should you merge only rebased branches?

Imagine the following situation:

* You start a new branch `feature-1` from the main and get to work on a new feature
* Your colleague also starts a new branch `fix-2` at the same time and has their merge request integrated before you
* You finish your work and want to have it integrated

![sample-merge-request-graph](/content/twice-as-fast-deployments-git-graph-with-merge-feature-branch.svg)

The master's pipeline is green, and the pipeline for `feature-1` is also green, but after you merge it into the master, the build fails - what happened? You and your colleague have created a conflict in the application logic without causing a git conflict, but since you've never tested those two changes together, there was no way for you to discover this problem. This is very common, and it's why you want to allow merging only rebased branches.

If you rebase your branch and let the CI run again, you can clearly see from the graph that now you're testing those changes together correctly.

![sample-merge-request-graph](/content/twice-as-fast-deployments-git-graph-with-merge-feature-branch-rebased.svg)

## But aren't the builds on main duplicates?

Great question - and yes, they are! The build on your branch should be exactly the same as the build on the main will be - it's by design to ensure predictability. But it's also wasteful since you're doing the same work twice. This is why sometimes if you really need to get that hotfix into the production as fast as possible, you're tempted to commit directly to the main without the ceremony of the branch and merge request.

You might see committing to the main as a way to shave off those extra 15 minutes, but I see it as a problem. After all, without running the entire build locally, you're risking that the pipeline on the main will fail because you've overlooked something in the stress of the outage. Now you have a failing build on main in addition to the bug on production - you haven't saved any time because you have to do it all again and wait for the CI once more. Was it worth it?

## You've promised me faster deploys...

So what can we do to preserve the safety of using merge requests without re-architecting the whole CI pipeline or completely changing workflows?

I'm proposing that if you can verify that the commit you're building was already successfully built, you can copy the resulting artefacts from the previous build. In my case, this can be achieved when all the following are true:

* The Gitlab project is configured with
    * Merge method - "Merge commit with semi-linear history"
    * Squash commits when merging - "Do not allow"
    * Merge checks - "Pipelines must succeed"
    * Protected branches - nobody can push to main, only merges are allowed
* The build output artefacts are always the same and are not configured build-time
    * This might not be true when you're baking in some config options or secrets into the artefact conditionally only on the main

Now, what does it mean to copy the artefacts? In my case, the build produces several docker images (the application and database with prepared data for integration testing for the frontend) and a directory with generated API docs HTML website. To have a baseline, let's imagine we have the following jobs:

* `job-build` - Runs the full maven build, which includes static analysis, tests and generating the API docs. The docker images are tagged with a commit hash.
* `job-deploy-production` - Takes the docker images tagged with a commit hash and pushes them tagged with `production` tag. Then the service is given a signal to redeploy the app.
* `job-deploy-docs` - Takes the HTML website and publishes it to S3, which allows it to be served by some proxy or CDN.

If we omit most of the configuration, you can image it as something similar to:

```yml
variables:
  IMAGE_APP_COMMIT: $CI_REGISTRY_IMAGE/app:$CI_COMMIT_SHA
  IMAGE_APP_PROD: $CI_REGISTRY_IMAGE/app:production

job-build:
  stage: build
  script:
    # run the full build
    - docker-compose run --rm mvn-full-build
    # create docker images
    - docker-compose build app
    # publish runtime images
    - docker push -q ${IMAGE_APP_COMMIT}
  artifacts:
    paths:
      - 'rest-api/target/generated-docs/*'

job-deploy-production:
  stage: deploy
  rules: # run only on main
    - if: '$CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH'
  script:
    # release PROD images
    - docker pull -q ${IMAGE_APP_COMMIT}
    - docker tag ${IMAGE_APP_COMMIT} ${IMAGE_APP_PROD}
    - docker push -q ${IMAGE_APP_PROD}
    # redeploy ECS services
    - ecs-deploy service-name

job-deploy-docs:
  stage: deploy
  rules: # run only on main
    - if: '$CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH'
  dependencies: [ 'job-build' ]
  script:
    # deploy the API docs
    - aws s3 cp ./rest-api/target/generated-docs s3://bucket/path --recursive
```

As you can see, the `job-build` runs for every commit, but the deploy jobs run only on main.

## Step 1: Conditional builds

Firstly, a condition based on the branch name is no longer enough. We need another dimension to decide if a commit is from a successfully built merge request. There are multiple ways to solve it, but since my workflow forces merge commits for every merge request, I can use that convention to construct a detection mechanism.

If I want to run a job on the main, but only for merge requests:

```yml
rules:
  - if: '$CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH && $CI_COMMIT_MESSAGE =~ /.*?Merge branch .*? into .*?(\n|.)+merge request [^ !]+!([0-9]+).*?/'
    when: on_success # run ONLY on merge request commits
```

If I want to run a job in all other cases:

```yml
rules:
  - if: '$CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH && $CI_COMMIT_MESSAGE =~ /.*?Merge branch .*? into .*?(\n|.)+merge request [^ !]+!([0-9]+).*?/'
    when: never # do not run on merge commits
  - when: on_success # otherwise OK
```

If you manage to trigger a build on the main on a commit that is not a merge commit, or if you push to the main directly, the build will fall back to the previous ineffective build strategy.

## Step 2: Copy the artefacts

When the rules for the merge commit are met, the build is not executed on the main anymore. So let's introduce another job that copies the merge request's artefacts. And since this will not be a bash oneliner, I'll move it to a separate bash script that [can be checked with the ShellCheck](https://github.com/koalaman/shellcheck) or a similar tool.

```yml
job-merged-pipeline-accelerate:
  stage: build
  script:
    - ./.gitlab/mirror-pipeline-results-from-merge-request.sh
  rules:
    - if: '$CI_COMMIT_BRANCH == $CI_DEFAULT_BRANCH && $CI_COMMIT_MESSAGE =~ /.*?Merge branch .*? into .*?(\n|.)+merge request [^ !]+!([0-9]+).*?/'
      when: on_success
  artifacts:
    paths:
      - 'rest-api/target/generated-docs/*'
```

We're going to be calling Gitlab API, and we're going to need a token. In the past, using [`$CI_JOB_TOKEN`](https://docs.gitlab.com/ee/ci/jobs/ci_job_token.html) was not enough, and I had to create a scoped token on my project or group, which I then put in the project's ENVs as `$JOB_TOKEN_MIRROR_MERGE_REQUEST_RESULTS`. That may no longer be necessary, and the `$CI_JOB_TOKEN` could be enough, but in case it's not, you can easily create the specialized token and use that instead.

Now let's look at the copy script:

```sh
#!/usr/bin/env bash

set -e

# We are in a build on the main branch, and we need to get the merge request ID
export MERGE_REQUEST_IID=$(echo ${CI_COMMIT_MESSAGE//$'\n'/ } | sed -E 's/.*See merge request [^! ]+!([0-9]+)([^0-9].*)?$/\1/')
curl --output /tmp/merge_request.json -f -s -S -H "PRIVATE-TOKEN: ${CI_JOB_TOKEN}" \
    "${CI_API_V4_URL}/projects/${CI_PROJECT_ID}/merge_requests/${MERGE_REQUEST_IID}"

# extract commit hash
export MR_HEAD_COMMIT_SHA=$(cat /tmp/merge_request.json | jq -r '.diff_refs.head_sha')
# extract last pipeline ID
export MR_PIPELINE_ID=$(cat /tmp/merge_request.json | jq -r '.head_pipeline.id')

echo "Determined that ${CI_COMMIT_SHORT_SHA} was result of merging ${MR_HEAD_COMMIT_SHA} after pipeline ${MR_PIPELINE_ID} passed"

# inspect the pipeline jobs
curl --output /tmp/pipeline_jobs.json -f -s -S -H "PRIVATE-TOKEN: ${CI_JOB_TOKEN}" \
    "${CI_API_V4_URL}/projects/${CI_PROJECT_ID}/pipelines/${MR_PIPELINE_ID}/jobs"

# find the ID of the job that produces the file artifacts we're interested in
export MR_API_DOCS_JOB_ID=$(cat /tmp/pipeline_jobs.json | jq -r '.[] | select(.name == "job-build") | .id')

echo "Unpacking artifacts from job ${MR_API_DOCS_JOB_ID} that should contain API docs"

curl --output /tmp/artifacts.zip -f -s -S -H "PRIVATE-TOKEN: ${CI_JOB_TOKEN}" \
    "${CI_API_V4_URL}/projects/${CI_PROJECT_ID}/jobs/${MR_API_DOCS_JOB_ID}/artifacts"

# unpacks in the exactly same directory structure, as it was in the job-build
unzip -o /tmp/artifacts.zip -d .

echo "Mirroring docker images from MR"

# repeat the following operations for any number of images you might want to mirror
export IMAGE_APP_MR_COMMIT="${CI_REGISTRY_IMAGE}/app:${MR_HEAD_COMMIT_SHA}"
echo "Mirroring ${IMAGE_APP_MR_COMMIT} -> ${IMAGE_APP_COMMIT}"
docker pull -q ${IMAGE_APP_MR_COMMIT}
docker tag "${IMAGE_APP_MR_COMMIT}" "${IMAGE_APP_COMMIT}"
docker push -q "${IMAGE_APP_COMMIT}"
```

You might notice that we've only downloaded and unpacked the file artifacts and just left them there. That's because we can use Gitlab CI's native artifact handling, which is defined in the `job-merged-pipeline-accelerate`.

Now only one small change remains, and that is to tell the `job-deploy-docs` that it can take the artifacts not only from the `job-build` but also from the `job-merged-pipeline-accelerate`:

```yml
job-deploy-docs:
  dependencies:
    - 'job-build'
    - 'job-merged-pipeline-accelerate'
```

## That's all?

Let's review the current state:
* We didn't need to change our workflow or completely rearchitect the entire build
* We've kept the safety of merge requests.
* Builds in merge requests are identical as they were previously.
* Builds on merge commits on main don't run heavy jobs anymore - they only copy the results from a merge request. Depending on the size of the artefacts and how fast you can start simple jobs, the build on main can now take a few seconds to a few minutes.
* We're no longer tempted to commit directly to the main to "not have to wait so long" - it's simply no longer worth the risk of breaking the main because you'd only save a few seconds at most.
* And obviously, this technique can be combined with other methods to reduce build times.

Just by having the ability to call the CI's API, we can create all kinds of automation. I have a few more techniques to reduce build times up my sleeve, but we'll look at them another time. In the meantime, if you have any clever tricks of your own, I'm eager to have a look if you're willing to share!
