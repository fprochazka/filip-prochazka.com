---
layout: blogpost
title: "Importing your existing AWS Route53 records into Terraform"
permalink: blog/importing-your-existing-aws-route53-records-into-terraform
date: 2021-10-17 09:50
tag: ["Terraform", "AWS", "Route53"]
---

When you start with a cloud, you rarely get everything just right on the first try. Most projects begin with IaaC after they've already been using AWS for some time - which means
you'll have a bunch of resources that have been created using the AWS Console, and they [have to be imported into Terraform](https://www.terraform.io/docs/cli/import/index.html).

Unfortunately, Route53 is extra tricky because you can easily create *a lot* of resources. It can quickly become unbearable to manually import because, as with any Terraform
resource, you have to first write the definition, and then you run the import command over and over for each resource individually... Or do you?

<!--more-->
## The manual way

Let's start from scratch to illustrate the difference in the comfort of the methods.

You have a Route53 zone with a lot of records. First, you have to write the resource definition itself.

~~~ tf
resource "aws_route53_record" "cogvio_com_MX" {
  zone_id = aws_route53_zone.cogvio_com.zone_id
  name = "cogvio.com."
  type = "MX"
  ttl = 300
  records = [
    "1 aspmx.l.google.com",
    "5 alt1.aspmx.l.google.com",
    "5 alt2.aspmx.l.google.com",
    "10 aspmx2.googlemail.com",
    "10 aspmx3.googlemail.com",
  ]
}
~~~

Now that you have the definition, you can tell Terraform that the record already exists, and it should not try to create it but only import it.

~~~ shell
terraform import aws_route53_record.cogvio_com_MX Z4KAPRWWNC7JR_cogvio_com_MX
~~~

You wait a bit, and Terraform loads the state. Next time you call `terraform apply`, changes to this resource will be reflected.

Now imagine doing this for 100 records. It's not even about the amount of work - at that size, it's about the mistakes you'll make doing this manually.

## Faster import of records

Most (if not all) resources are unique by some identifier, and AWS won't allow you to create a second resource with the same identifier. In Route53, the records are unique based on
the type and name. If you'd try to run `terraform apply` without the import, Terraform would try to create the `name="cogvio.com.", type="MX"` record and AWS would return an error.

But `aws_route53_record` is special because it has the `allow_overwrite` argument. You specify it, and the first time you run `terraform apply`, Terraform will try to create the
resource, but it won't fail; instead, it will overwrite the resource and save it into its state. This means you have to only somehow write the resources, run `terraform apply`
once, and you're done!

## Faster writing of record resources

Since Terraform will overwrite the records, you really have to write them correctly on the first try. Thankfully, AWS has a cli client that can dump all the Route53 routes in a
single JSON request.

~~~ shell
aws route53 list-resource-record-sets --hosted-zone-id Z4KAPRWWNC7JR \
  --max-items 1000 --page-size 1000 > route53_cogvio_com.json
~~~

Having this dump is essential for two reasons. First, if you mess up the Terraform import, you have a backup that you can restore. And second - we're going to use this JSON to
generate the resources with a bit of Python.

~~~ py
import json
import re
from pathlib import Path
from typing import Any, Dict, List

project_dir = Path('/home/fprochazka/devel/projects/cogvio/infrastructure/shared')

domain = 'cogvio.com'
domain_snake = domain.replace('.', '_')
input_file = project_dir / f'route53_{domain_snake}.json'
tf_output_file = project_dir / f'route53_{domain_snake}_records.tf'

with open(input_file) as f:
    existing_records = json.load(f)


def rec_contains(resource_records: List[Dict[str, Any]], needle: str) -> bool:
    for item in resource_records:
        if needle in item['Value']:
            return True
    return False


with open(tf_output_file, mode='w', encoding='utf-8') as out:
    for record_set in existing_records['ResourceRecordSets']:
        r_type = record_set.get('Type')
        if r_type == 'NS' or r_type == 'SOA':
            continue

        r_name: str = record_set.get('Name')
        r_ttl: int = record_set.get('TTL') or 1800
        r_records = record_set.get('ResourceRecords')
        r_alias = record_set.get('AliasTarget')

        if r_alias:
            r_hosted_zone_id = r_alias.get('HostedZoneId')
            r_dsn_name = r_alias.get('DNSName')

            if '.elb.amazonaws.' in r_dsn_name:
                resource_name = 'aws_elb_' + r_name.replace(f'{domain}.', '')
            else:
                resource_name = r_name.replace(f'{domain}.', '')

            resource_name = re.compile(r'[_.-]+').sub('_', domain_snake + '_' + resource_name + '_' + r_type).strip('_')

            out.write(f'resource "aws_route53_record" "{resource_name}" ' + "{\n")
            out.write(f'  zone_id = aws_route53_zone.{domain_snake}.zone_id' + "\n")
            out.write(f'  name = "{r_name}"' + "\n")
            out.write(f'  type = "{r_type}"' + "\n")
            out.write('  alias {' + "\n")
            out.write(f'    name = "{r_dsn_name}"' + "\n")
            out.write(f'    zone_id = "{r_hosted_zone_id}"' + "\n")
            out.write(f'    evaluate_target_health = false' + "\n")
            out.write('  }' + "\n")
            out.write(f'  allow_overwrite = true' + "\n")
            out.write('}' + "\n")

        else:
            if rec_contains(r_records, 'dkim.amazonses'):
                resource_name = 'amazonses_dkim_' + r_name.replace(f'{domain}.', '').replace('_domainkey', '')
            elif rec_contains(r_records, 'acm-validations'):
                resource_name = 'aws_acm_' + r_name.replace(f'_domainkey.{domain}.', '')
            else:
                resource_name = r_name.replace(f'{domain}.', '')

            resource_name = re.compile(r'[_.-]+').sub('_', domain_snake + '_' + resource_name + '_' + r_type).strip('_')

            out.write(f'resource "aws_route53_record" "{resource_name}" ' + "{\n")
            out.write(f'  zone_id = aws_route53_zone.{domain_snake}.zone_id' + "\n")
            out.write(f'  name = "{r_name}"' + "\n")
            out.write(f'  type = "{r_type}"' + "\n")
            out.write(f'  ttl = {r_ttl}' + "\n")
            out.write(f'  records = [' + "\n")
            for record_item in r_records:
                r_i_val = record_item["Value"].strip('"')
                out.write(f'    "{r_i_val}",' + "\n")
            out.write(f'  ]' + "\n")
            out.write(f'  allow_overwrite = true' + "\n")
            out.write('}' + "\n")

        out.write("\n")
~~~

It's not pretty, but I'll probably only run this once and never again.

The script expects the JSON with routes to be in the `route53_cogvio_com.json` file, and it generates the output into `route53_cogvio_com_records.tf`.

The script also tries to generate semi-usable names for the resources. You should be able to modify it further if you have a convention in mind. Or simply run it and fix the names
manually - it's still less work than writing it manually from scratch.

## Putting it all together

You should first write the definition for the Route53 zone and import it. This would be [a waste to automate](https://xkcd.com/1205/) since it's only a single resource (per domain)
.

~~~ tf
# terraform import aws_route53_zone.cogvio_com Z4KAPRWWNC7JR
resource "aws_route53_zone" "cogvio_com" {
  name = "cogvio.com"
}
~~~

The script skips `NS` and `SOA` records because I want them to be a bit more dynamic. These are written with `allow_overwrite`, so you shouldn't have to import them.

~~~ tf
resource "aws_route53_record" "cogvio_com_nameservers" {
  zone_id = aws_route53_zone.cogvio_com.zone_id
  name = "${aws_route53_zone.cogvio_com.name}."
  type = "NS"
  ttl = 172800
  records = [
    "${aws_route53_zone.cogvio_com.name_servers[0]}.",
    "${aws_route53_zone.cogvio_com.name_servers[1]}.",
    "${aws_route53_zone.cogvio_com.name_servers[2]}.",
    "${aws_route53_zone.cogvio_com.name_servers[3]}.",
  ]
  allow_overwrite = true
}

resource "aws_route53_record" "cogvio_com_soa" {
  zone_id = aws_route53_zone.cogvio_com.zone_id
  name = "${aws_route53_zone.cogvio_com.name}."
  type = "SOA"
  ttl = 900
  records = [
    "${aws_route53_zone.cogvio_com.name_servers[0]}. awsdns-hostmaster.amazon.com. 1 7200 900 1209600 86400",
  ]
  allow_overwrite = true
}
~~~

Now we can run the `aws cli` to dump the existing records. I'd suggest versioning the JSON file in a git repo, so you can run the `aws cli` again after you've finished to easily
diff the changes.

Next, you run the python script to generate the resource definitions and review them. You might want to fix some names at this point.

After reviewing the resources, you run `terraform apply`, confirm the plan and wait.

If everything goes well, now is the time to run the `aws cli` command again to diff the records. In my case, a few of the records with multiple values were reordered, but
everything else was as expected.

The final step is to use your text editor to remove `allow_overwrite = true` arguments from all the resource definitions, as it shouldn't be needed anymore.

## Gotchas

Please review the [TXT records](https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/ResourceRecordTypes.html#TXTFormat) the script generates extra carefully.
Terraform [automatically surrounds the values for TXT records in quotes](https://registry.terraform.io/providers/hashicorp/aws/latest/docs/resources/route53_record#records), but
extra-long records like Google's DKIM might have to be split up using `\"\"`.

## Why didn't you just...?

I have considered using [terraformer](https://github.com/GoogleCloudPlatform/terraformer), but writing few lines of Python seemed simpler than learning an entirely new tool. Also,
having to comb through the resources manually when I'm importing them gives me a chance to review past choices and fix mistakes - our infra is still tiny, so this is still viable.
