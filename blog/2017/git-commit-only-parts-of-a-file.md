Date: 2017-07-21 23:28
Tags: Git

# Git: commit only parts of a file

This article will explore ways to commit parts of a file separately.
Why you should strive to have a useful commit history is a topic of itself and we might explore it later.
I'm aware of two ways to do it and I'm gonna share them with you in here.

## git add -p

In order to commit the changes, we have to first stage the changes which is done using the [`git-add` command](https://git-scm.com/docs/git-add).
And if you want to commit only parts the file, you can use the interactive mode, which is turned on by the `-p` option.
It continuously shows small portions of the changed files and asks you what to do.
In each step, you can mark hunks, which is a nearby set of changes, for staging or to be ignored for now.

![git-add-p](/content/git-add-p.gif)

Most of the commands in this mode are self-explanatory.
When lost, you only have to type the `?` symbol, which lists the commands and explains what they do.
Let's focus on the following two commands:

```
s - split the current hunk into smaller hunks
e - manually edit the current hunk
```

In the above animation, you can see the `s` in action. When two or more of the changes are close to each other, Git shows them as one hunk.
If they're separated by some not modified lines, you can commit them separately, just tell Git to show smaller hunks using the `s` command.
However, this doesn't allow you to commit individual modified lines that are right next to other modified lines.

Luckily, you can use the `e` command! It opens the current hunk as an editable patch and you can modify what will get staged and what not.

![git-add-edit-patch](/content/git-add-edit-patch.png)

This is extremely powerful, but editing the patch manually can get annoying pretty fast.

## Git Cola

Meet the [Git Cola](https://git-cola.github.io/)! It has many features, but what I love most about it is its ability to stage separate lines.

![git-cola](/content/git-cola.png)

A bit weird is that you have to actually select at least one symbol to make the "Stage Selected Lines" action work.
If you don't select anything, it will stage the whole file.

I also have to admit, that I find it a bit confusing, so you might notice, that mine looks a bit different from the default setup after installation. I've disabled all panels, except the diff panel and panel of changed and untracked files. I do not commit from it, but hey, it might work for you - I'm not gonna stop you :)

Big thanks belong to [Vladimír Kriška](https://twitter.com/ujovlado) who showed me this program, so [follow him on twitter](https://twitter.com/ujovlado)! :)

## IntelliJ PhpStorm, IDEA, etc.

As of writing this article, there is an open issue [IDEA-63201](https://youtrack.jetbrains.com/issue/IDEA-63201) requesting this feature, meaning there is no native way of doing this operation in PhpStorm, IDEA and other IntelliJ IDE's.

Currently, the most pleasant way to achieve this is using Git Cola.
If you set it up as an external tool, you can not only open the program from IntelliJ IDE's, but you can open it using an arbitrary shortcut.

![git-cola-phpstorm-external-tools](/content/git-cola-phpstorm-external-tools.png)

After you have the external tool configured, go to `Settings > Keymap > External Tools` and configure a shortcut.

Once you want to commit something, hit your shortcut, stage the changes and close Git Cola using `CTRL+Q` so you can commit from the IDE.

## Conclusion

We've explored two easy ways to commit parts of a file and how to integrate them into your IDE workflow.
Do you know any other way to do this? Or better ways to use the presented tools? Comment down below.
