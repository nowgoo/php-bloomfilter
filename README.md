# php-bloomfilter
a php bloom filter implement, using internal string or redis for storage.

## basic usage
~~~
require('BloomFilter');
$bf = BloomFilter::factory();
foreach(['foo', 'bar', 'baz'] as $v) {
  $bf->add($v);
}
assert($bf->exist('foo') === true);
assert($bf->exist('not_exists') === false);
~~~

## options
~~~
$bf = BloomFilter::factory(BloomFilter::STORAGE_BY_STRING, [
  'size' => 81920, // in bits, takes ~10K memory
  'k' => 8, // hash rounds
]);
~~~

## using redis bitmap for storage
~~~
$redis = new Redis(...);
$bf = BloomFilter::factory(BloomFilter::STORAGE_BY_REDIS, [
  'redis' => $redis, // redis instance
  'key' => 'bf-key', // redis key
]);
~~~
