---
layout: blogpost
title: "Sharing your IDEA settings within a team"
permalink: blog/sharing-your-idea-settings-within-a-team
date: 2017-12-03 20:20
tag: ["IDE", "Code Quality"]
---

When working in a team, it is essential everyone has the same local setup. You do need to enforce the agreed-upon rules on CI level, but it is ideal when you discover the problems even before you commit your code. That is where IDEA inspections and Code Style steps in.

<!--more-->
## IDEA Inspections

IDEA [Code Inspections](https://www.jetbrains.com/help/idea/code-inspection.html) discover code smells and bugs in real time, while you are writing your code. By the way, this is one of the reasons, why you should be using an IDE, not a text editor (like Sublime Text or Vim) for writing code.  There are many checks you can reconfigure, and you can also create profiles with different settings and therefore even have different profiles for different projects.

It is frustrating when you checkout branch of your colleague to do a code review, and the code in IDEA starts glowing with red and yellow. How did they miss this? Easily - you do not have the same settings!

Luckily, Code Inspections can be easily exported and imported. You just have to know where to find the actions.

![git-add-p](/content/intellij-idea-profiles-export.png)

## Code Style

[Code Style](https://www.jetbrains.com/help/idea/configuring-code-style.html) is also something you can easily export and share. Moreover, when you happen to be using Java, like me, you can even import [CheckStyle](https://github.com/checkstyle/checkstyle#readme) configuration! This way, you get code reformating to match coding style of your team and your CI basically for free.

![git-add-p](/content/intellij-idea-code-style-import.png)

## Share the settings

When you have the settings exported, the last step is to commit them to your project. If you do not know where to put the inspections, just create a `docs/` directory and put them there.

Now every time your team decides to change project guidelines, you can just change it, export it, commit it and tell your colleagues to import the latest settings. Furthermore, when you hire someone new, just tell them to import the settings from the project repository, and they are good to go! It is a living documentation :)

Tell me in the comments, if you are already sharing the settings in your team or if you will start, as the first thing tomorrow :) Also, do you know any other useful settings that can be exported and imported others might not know about?
