<?php
namespace Psalm\Provider;

use Psalm\Config;
use Psalm\Storage\FileStorage;

class FileStorageCacheProvider
{
    /**
     * @var string
     */
    private $modified_timestamps = '';

    /**
     * @var Config
     */
    private $config;

    const FILE_CACHE_DIRECTORY = 'file_cache';

    public function __construct(Config $config)
    {
        $this->config = $config;

        $storage_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR;

        $dependent_files = [
            $storage_dir . 'FileStorage.php',
            $storage_dir . 'FunctionLikeStorage.php',
        ];

        foreach ($dependent_files as $dependent_file_path) {
            if (!file_exists($dependent_file_path)) {
                throw new \UnexpectedValueException($dependent_file_path . ' must exist');
            }

            $this->modified_timestamps .= ' ' . filemtime($dependent_file_path);
        }
    }

    /**
     * @param  string $file_path
     * @param  string $file_contents
     *
     * @return void
     */
    public function writeToCache(FileStorage $storage, $file_contents)
    {
        $file_path = strtolower($storage->file_path);
        $cache_location = $this->getCacheLocationForPath($file_path, true);
        $storage->hash = $this->getCacheHash($file_path, $file_contents);

        file_put_contents($cache_location, serialize($storage));
    }

    /**
     * @param  string $file_path
     * @param  string $file_contents
     *
     * @return FileStorage|null
     */
    public function getLatestFromCache($file_path, $file_contents)
    {
        $cached_value = $this->loadFromCache($file_path);

        if (!$cached_value) {
            return null;
        }

        $cache_hash = $this->getCacheHash($file_path, $file_contents);

        if ($cache_hash !== $cached_value->hash) {
            unlink($this->getCacheLocationForPath($file_path));

            return null;
        }

        return $cached_value;
    }

    /**
     * @param  string|null $file_path
     * @param  string|null $file_contents
     *
     * @return string
     */
    private function getCacheHash($file_path, $file_contents)
    {
        return sha1($file_path . ' ' . $file_contents . $this->modified_timestamps);
    }

    /**
     * @param  string  $file_path
     *
     * @return FileStorage|null
     */
    private function loadFromCache($file_path)
    {
        $cache_location = $this->getCacheLocationForPath($file_path);

        if (file_exists($cache_location)) {
            /** @var FileStorage */
            return unserialize((string)file_get_contents($cache_location)) ?: null;
        }

        return null;
    }

    /**
     * @param  string  $file_path
     * @param  bool $create_directory
     *
     * @return string
     */
    private function getCacheLocationForPath($file_path, $create_directory = false)
    {
        $root_cache_directory = $this->config->getCacheDirectory();

        if (!$root_cache_directory) {
            throw new \UnexpectedValueException('No cache directory defined');
        }

        $parser_cache_directory = $root_cache_directory . DIRECTORY_SEPARATOR . self::FILE_CACHE_DIRECTORY;

        if ($create_directory && !is_dir($parser_cache_directory)) {
            mkdir($parser_cache_directory, 0777, true);
        }

        return $parser_cache_directory . DIRECTORY_SEPARATOR . sha1($file_path);
    }
}