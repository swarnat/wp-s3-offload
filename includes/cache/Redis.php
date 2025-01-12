<?php
namespace WPS3\S3\Offload\Cache;

use Aws\CacheInterface;

class Redis implements CacheInterface
{
    private \Redis $redis;

    public function __construct()
    {

        $this->redis = new \Redis();
        $this->redis->pconnect(constant("WP_REDIS_HOST"), 6379);
        
    }

    private function getCacheFilePath(string $key): string
    {
        return constant("WP_REDIS_PREFIX") . sha1($key) . '.cache';
    }

    public function get($key)
    {   
        $file = $this->getCacheFilePath($key);

        $data = $this->redis->get($file);
        if ($data === false) {
            return null;
        }

        $content = json_decode($data, true);

        return $content;
    }

    public function set($key, $value, $ttl = null): void
    {
        $file = $this->getCacheFilePath($key);
        $expiration = $ttl !== null ? time() + $ttl : 0;
        var_dump("REDIS-SET", $key);
        $dataString = json_encode($value);

        if ($this->redis->setEx($file, $dataString, ['nx', 'ex' => 3600]) !== true) {
            throw new \RuntimeException(sprintf('Failed to write cache file: %s', $file));
        }
    }

    public function remove($key): void
    {
        $file = $this->getCacheFilePath($key);

        if ($this->redis->unlink($file)) {
            throw new \RuntimeException(sprintf('Failed to remove cache file: %s', $file));
        }
    }
}