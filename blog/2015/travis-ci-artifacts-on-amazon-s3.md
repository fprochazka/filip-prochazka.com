Date: 2015-02-14 23:55
Tags: AWS, Travis-CI

# Travis-CI: artifacts on Amazon S3

Protože jsme byli líní si nastavovat Jenkinse, tak používáme [Travis-CI](https://travis-ci.com/) pro [rohlík](https://www.rohlik.cz/) a dneska jsem zjistil, že Travis vám vypíše maximálně ~10k řádků výstupu :)

![travis-artifacts-10k-lines](/content/travis-artifacts-10k-lines.png)

Co s tím? No, bude potřeba ten "bordel" z buildu nahrát někam jinam.

Když náhodou narazíte na tu správnou kombinaci slov, tak se vám poštěstí najít [dva](http://blog.travis-ci.com/2012-12-18-travis-artifacts/) [články](http://docs.travis-ci.com/user/uploading-artifacts/). Bohužel jsou oba dva úplně blbě a jenom s nimi budete ztrácet čas. Já jsem se po pár hodinách dopracoval k následujícímu řešení.


## S3: nový kýblík

Zaregistrovat se na Amazon a vytvořit nový kýblíček je velice primitivní. Jediné gotcha, na které jsem narazil, tak je povolit, aby z bucketu šlo číst přes veřejné url. Návod jak to udělat jsem [našel tady](http://stackoverflow.com/a/4709391).

Vlezeme si do "Properties" bucketu, roletka "Permissions", prostřední tlačítko "Add bucket policy"

```js
{
	"Version": "2008-10-17",
	"Statement": [
		{
			"Sid": "AllowPublicRead",
			"Effect": "Allow",
			"Principal": {
				"AWS": "*"
			},
			"Action": "s3:GetObject",
			"Resource": "arn:aws:s3:::muj-travis/*"
		}
	]
}
```

a nezapomenout nahradit `muj-travis` za název našeho kýblíčku.



## S3: vytvoření nového uživatele

Travis bude potřebovat nějak nahrávat ty soubory na Amazon. No a na Amazonu je dobrým pravidlem na všechno dělat nového uživatele, který vždy dostane nová přístupová práva a dáte mu jenom ta opravnění, jaká potřebuje.

Najdeme si tedy [IAM](https://console.aws.amazon.com/iam/home?region=eu-west-1#users) (= Identity and Access Management), vytvoříme nového uživatele a buďto přímo uživateli přidáme novou Policy, nebo pro něj vytvoříme Group a té dáme následující policy

```js
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": [
        "s3:ListBucket"
      ],
      "Effect": "Allow",
      "Resource": [
        "arn:aws:s3:::muj-travis"
      ]
    },
    {
      "Action": [
        "s3:PutObject",
        "s3:PutObjectAcl"
      ],
      "Effect": "Allow",
      "Resource": [
        "arn:aws:s3:::muj-travis/*"
      ]
    }
  ]
}
```

a nezapomenout nahradit `muj-travis` za název kýblíčku.


## Travis: úprava buildu

Ze současného

```neon
after_failure:
  - 'for i in $(find ./tests -name \*.actual); do echo "--- $i"; cat $i; echo; echo; done'

after_script:
  - 'for i in $(find ./log -name \*.log); do echo "--- $i"; cat $i; echo; echo; done'
  - 'for i in $(find ./tests -name \*.log); do echo "--- $i"; cat $i; echo; echo; done'
```

smažeme blok `after_script` a nahradíme ho za

```neon
after_script:
  - curl -sL https://raw.githubusercontent.com/travis-ci/artifacts/master/install | bash
  - tests/travis.artifacts.sh
```

Vytvoříme soubor `tests/travis.artifacts.sh` s obsahem

```shell
#!/bin/bash

# export ARTIFACTS_DEBUG=1
export ARTIFACTS_S3_REGION=eu-west-1
export ARTIFACTS_BUCKET=muj-travis
export ARTIFACTS_TARGET_PATHS=artifacts/$TRAVIS_REPO_SLUG/$TRAVIS_BUILD_ID/$TRAVIS_JOB_ID

~/bin/artifacts upload --key my-s3-key-id --secret my-s3-secret \
  $(ls log/*.log tests/**/*.log tests/**/exception-*.html 2> /dev/null | tr "\n" ":" | sed -r 's/:$//')
```

nahradíme `my-s3-key-id` za přístupový klíč našeho uživatele, `my-s3-secret` za secret a `muj-travis` za název kýblíčku.
Pokud používáte jiný region tak i `eu-west-1`, ale `eu-centra-1` mi z nějakého důvodu nefungoval, tak jsem to nechal v Irsku.

Ano, vidíte správně, commitnul jsem do repa klíč a secret ke svému S3 bucketu v plaintextu. Proč bych neměl takovou věc dělat? Věděli jste, že existují roboti, kteří prochází veřejné repozitáře a hledají commitnuté klíče a pak je zneužívají na šíření spamu a virů? :) No a proto [je dobrý nápad commitovat zašifrované klíče](http://docs.travis-ci.com/user/encryption-keys/), nebo použít nastavení [Environment Variables](http://docs.travis-ci.com/user/environment-variables/#Secure-Variables) repozitáře.

Proč teda sakra commituju ty klíče v plaintextu? Vyvíjíme [feature branchingem a na všechno děláme pullrequesty](https://guides.github.com/introduction/flow/). No a Travis má bezpečnostní feature, že nedovolí použít v buildu pullrequestu zašifrované parametry a je jedno jestli je tam vložít přes "Environment Variables" v nastavení repozitáře, nebo [přes cli utilitu](http://blog.travis-ci.com/2013-01-14-new-client/) na kterou potřebujete ruby. Je to asi kvůli tomu, že bych si mohl do pullrequestu dát nějaké echo a vypsat si ty klíče v plaintextu a pak je zneužít. To chápu. Co nechápu, tak že tohle není možné použití ani u Travis-PRO pro privátní repozitáře, kde je naprosto jasné, že to používají pouze a jenom lidé z firmy a je jedno co s pullrequestem udělám za šaškárny.

Jako vždy, budu rád když mě vyvedete z omylu, pokud existuje lepší řešení :)
