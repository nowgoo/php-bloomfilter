<?php
/**
 * a php bloom filter
 * author: nowgoo@gmail.com
 * date: 2016-11-25 15:11
 */

class BloomFilter
{
    const STORAGE_BY_STRING = 0;
    const STORAGE_BY_REDIS = 1;

    /**
     * @var BloomFilterStringStorage
     */
    public $storage = '';

    /**
     * bit array size
     * @var int
     */
    public $size;

    /**
     * hash rounds
     * @var int
     */
    public $k;

    /**
     * init an instance
     * @param BloomFilterStringStorage $storage
     * @param int $size bit array size
     * @param int $k hash rounds
     */
    public function __construct($storage, $size, $k=8)
    {
        $this->size = $size;
        $this->k = $k;
        $this->storage = $storage;
    }

    /**
     * factory method
     * @param int $storage STORAGE_BY_*
     * @param array $params
     * @return BloomFilter
     */
    public static function factory($storage=self::STORAGE_BY_STRING, $params=[])
    {
        $size = isset($params['size']) ? $params['size'] : 81920;
        $k = isset($params['k']) ? $params['k'] : 8;
        if ($storage == self::STORAGE_BY_STRING) {
            return new BloomFilter(new BloomFilterStringStorage($size), $size, $k);
        } elseif ($storage == self::STORAGE_BY_REDIS) {
            return new BloomFilter(new BloomFilterRedisStorage($size, $params), $size, $k);
        }
    }

    /**
     * check for an item
     * @param string $item
     * @return bool
     */
    public function exist($item)
    {
        foreach ($this->offsets($item) as $offset) {
            if (!$this->storage->checkBit($offset)) {
                return false;
            }
        }
        return true;
    }

    /**
     * add an item
     * @param string $item
     */
    public function add($item)
    {
        foreach ($this->offsets($item) as $offset) {
            $this->storage->setBit($offset);
        }
    }

    /**
     * calculate offsets
     * @param string $item
     * @return array
     */
    public function offsets($item)
    {
        $hash1 = self::FNVHash($item);
        $hash2 = self::APHash($item);

        $ret = [];
        for ($i = 0; $i < $this->k; $i++) {
            $offset = ($hash1 + $i * $hash2) % $this->size;
            $ret[] = $offset >= 0 ? $offset : $offset + $this->size;
        }
        return $ret;
    }

    public static function FNVHash($key)
    {
        $prime = 0x811C9DC5;
        $hash = 0;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $hash *= $prime;
            $hash ^= ord($key[$i]);
        }
        return $hash;
    }

    public static function APHash($key)
    {
        $hash = 0xAAAAAAAA;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            if (($i & 1) == 0) {
                $hash ^= (($hash <<  7) ^ ord($key[$i]) * ($hash >> 3));
            } else {
                $hash ^= (~(($hash << 11) + ord($key[$i]) ^ ($hash >> 5)));
            }
        }
        return $hash;
    }
}

class BloomFilterStringStorage
{
    private $data = '';

    private $size = 0;

    public function __construct($size)
    {
        $this->size = $size;
        $this->data = str_repeat(chr(0), $size/8);
    }

    public function setBit($offset)
    {
        $o = $offset % 8;
        $i = intval(($offset - $o) / 8);
        $this->data[$i] = chr(ord($this->data[$i]) | (1 << 7-$o));
    }

    public function checkBit($offset)
    {
        $o = $offset % 8;
        $i = intval(($offset - $o) / 8);
        if ((ord($this->data[$i]) >> 7-$o & 1) ^ 1) {
            return false;
        }
        return true;
    }
}

class BloomFilterRedisStorage
{
    /**
     * @var \Redis
     */
    private $redis = null;

    /**
     * @var string
     */
    private $key = '';

    /**
     * @var int
     */
    private $size = 0;

    public function __construct($size, $params)
    {
        $this->size = $size;
        $this->redis = $params['redis'];
        $this->key = $params['key'];
    }

    public function setBit($offset)
    {
        $this->redis->setBit($this->key, $offset, 1);
    }

    public function checkBit($offset)
    {
        return $this->redis->getBit($this->key, $offset);
    }
}
