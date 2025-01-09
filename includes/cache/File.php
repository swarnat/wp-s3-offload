<?php
namespace WPS3\S3\Offload\Cache;

use Aws\CacheInterface;

class File implements CacheInterface
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to create cache directory: %s', $cacheDir));
        }

        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function getCacheFilePath(string $key): string
    {
        return $this->cacheDir . sha1($key) . '.cache';
    }

    public function get($key)
    {
        $file = $this->getCacheFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $data = file_get_contents($file);
        $content = json_decode($data, true);

        $expiration = $content["expire"];
        if ($expiration !== 0 && $expiration < time()) {
            unlink($file);
            return null;
        }

        return $content["content"];
    }

    public function set($key, $value, $ttl = null): void
    {
        $file = $this->getCacheFilePath($key);
        $expiration = $ttl !== null ? time() + $ttl : 0;

        $data = [
            "expire" => $expiration,
            "content" => $value
        ];

        $dataString = json_encode($data);

        if (file_put_contents($file, $dataString) === false) {
            throw new \RuntimeException(sprintf('Failed to write cache file: %s', $file));
        }
    }

    public function remove($key): void
    {
        $file = $this->getCacheFilePath($key);

        if (file_exists($file) && !unlink($file)) {
            throw new \RuntimeException(sprintf('Failed to remove cache file: %s', $file));
        }
    }
}