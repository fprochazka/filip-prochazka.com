---
layout: blogpost
title: "Optimizing PostgreSQL queries with Multicolumn and Partial Indexes"
permalink: blog/optimizing-postgre-sql-queries-with-multicolumn-and-partial-indexes
date: 2021-10-24 10:00
tag: ["PostgreSQL", "Performance"]
---

I have an application that does asynchronous data processing, and at the core of the application are simulated queues in a PostgreSQL table. Each row in that queue represents a
task and also contains the result of that task. You can imagine this table as a sort of multi-tenant where the rows belong to a `data_source` and `queue`. There are multiple
DataSources, and each can have multiple queues. Some of the combinations contain very few rows, and some of them contain several million.

This uneven distribution of rows caused that while some of the queues can be queried rather quickly, the largest queue has slowly grown in size to the point where the job iterating
over it took around 9 hours.

<!--more-->
## The problem

The relevant parts (there are more columns) of the table look like this.

~~~sql
CREATE TABLE queue_item
(
    id                  UUID         NOT NULL PRIMARY KEY,
    data_source         VARCHAR(255) NOT NULL,
    queue               VARCHAR(255) NOT NULL,
    status              VARCHAR(255) NOT NULL,
    resource_identifier VARCHAR(255) NOT NULL,
    extracted_data      JSONB,
    created_at          TIMESTAMP    NOT NULL,
    updated_at          TIMESTAMP    NOT NULL,
    parent_id           UUID
        CONSTRAINT queue_item_x_parent_id_x_fk
            REFERENCES queue_item,
    CONSTRAINT queue_item_x_unique_item
        UNIQUE (data_source, queue, resource_identifier)
);
CREATE INDEX queue_item_x_parent_id
    ON queue_item (parent_id);
CREATE INDEX queue_item_x_queue_x_status_x_created_at
    ON queue_item (data_source, queue, status, created_at);
~~~

The query that we're executing looks something like this

~~~sql
SELECT *
FROM (
  SELECT *
  FROM queue_item
  WHERE queue_item.data_source = 'BIG_CHUNGUS'
    AND queue_item.queue = 'FILES'
    AND queue_item.status != 'DEAD'
    AND queue_item.parent_id IN ('a1...', 'b2...')
) t
WHERE ((id > 'a718b1c3-3bb7...'))
ORDER BY id ASC
LIMIT 100;
~~~

As you can see, we're primarily filtering by the `data_source` and `queue` because that's relevant for this job. Then, we further limit the results by `status` and `parent_id` (the
table is recursive). This defines the scope of rows that the job is processing.

The query uses what is known as [keyset pagination](https://use-the-index-luke.com/no-offset), which is often much more performant than OFFSET pagination. With OFFSET pagination,
the performance degrades as the offset rises, making it unsuitable for iterating over large result sets. With keyset pagination, the first time the query is executed, the query
builder does not add the `WHERE id > 'xy'`, returning the first 100 results. The next time the query is executed, the query builder remembers the last row's `id` and uses it to
paginate. This repeats until the query stops returning rows.

PostgreSQL has very nice execution plan descriptions, so executing the query with the following `EXPLAIN` gave me a baseline.

~~~sql
EXPLAIN (ANALYZE, BUFFERS, VERBOSE, SETTINGS, WAL) SELECT ...
~~~

A minor complication is that there is no way to flush caches in PostgreSQL, so if you execute the same query repeatedly, it will have the disk pages cached in memory. The
consecutive executions will be much faster, hindering the reproducibility of the problem. In my case, I have millions of rows, so selecting an id that can be used for the keyset
pagination and is not in cache is not that hard.

## Optimizing with Multicolumn indexes

My first instinct was to try optimize with [Multicolumn indexes](https://www.postgresql.org/docs/13/indexes-multicolumn.html). But to my surprise, this index had minimal impact.

~~~sql
CREATE INDEX queue_item_x_1 ON queue_item (data_source, queue, status, parent_id, id);
~~~

So out of curiosity, I've tried several other combinations of columns to see if [the planner](https://www.postgresql.org/docs/13/planner-optimizer.html) would like a different
variation.

~~~sql
CREATE INDEX queue_item_x_2 ON queue_item (data_source, queue, status, id, parent_id);
CREATE INDEX queue_item_x_3 ON queue_item (data_source, status, queue, parent_id, id);
-- and so on
~~~

But nothing really helped, and the query was still very slow. I think this has something to do with the data distribution, but I'm not sure. Even the documentation mentions that
multicolumn indexes with more than 3 columns might not have a significant impact.

## Optimizing with Partial Indexes

At this point, I've realized that I don't really need a perfect index that would cover all the DataSources because all the other jobs were processed rather fast, and only this job
was causing problems.

Thankfully, PostgreSQL has a feature for exactly this use case, and it's called [Partial Indexes](https://www.postgresql.org/docs/13/indexes-partial.html). Basically, you specify a
filtering predicate in the index definition that tells PostgreSQL that only rows that match the predicate will be indexed. If you then use the same predicate in the query,
PostgreSQL knows it can use the index.

~~~sql
CREATE INDEX queue_item_x_big_chungus_files ON queue_item (parent_id, id)
    WHERE data_source = 'BIG_CHUNGUS'
      AND queue = 'FILES'
      AND status != 'DEAD';
~~~

This index still covers the majority of the table, but the contents and combinations of `data_source`, `queue` and `status` are not really stored in it's data structures. Also,
the `parent_id` column splits the table into relatively similar chunks with at most a few hundred thousand rows each, thanks to this queue's specific data distribution. Now, this
can be paginated very nicely, and the total runtime of the job was drastically reduced.

One final optimization that comes to mind is how many rows are returned at a time. The `LIMIT 100` is a reasonable default for most of the other jobs, but we can increase it a bit
for this one. I've experimented with a few values before I've settled on `LIMIT 1000`.

The much faster query in combination with 10 times less number of executions helped to reduce the job execution time from around 9 hours to just under 6 minutes.

I think I can live with that for a while.

