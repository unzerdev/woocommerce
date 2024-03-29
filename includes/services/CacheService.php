<?php

namespace UnzerPayments\Services;

class CacheService
{
    /**
     * @var string
     */
    private $dir = 'wp-content/cache/unzer';
    /**
     * @var bool
     */
    private $isActive;
    /**
     * @var string
     */
    private $salt;

    /**
     * @param string $salt
     */
    public function __construct($salt = '')
    {
        $this->isActive = $this->prepareCacheDirectory();
        $this->salt = $salt;
    }

    /**
     * @return bool
     */
    private function prepareCacheDirectory()
    {
        if (file_exists(ABSPATH . $this->dir)) {

            if (is_writable(ABSPATH . $this->dir)) {
                return true;
            }
            chmod(ABSPATH . $this->dir, 0771);
            if (is_writable(ABSPATH . $this->dir)) {
                return true;
            }
        }

        (new LogService())->warning('No writable log directory. I\'ll try to create one.');

        if (mkdir(ABSPATH . $this->dir, 0771, true)) {
            chmod(ABSPATH . $this->dir, 0771);
            if (file_put_contents(ABSPATH . $this->dir . '/.htaccess', "Order deny,allow\nDeny from all")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $key
     * @param $value
     * @return void
     */
    public function set($key, $value)
    {
        if (!$this->isActive) {
            return;
        }
        $path = $this->getFilePath($key);
        if (file_exists($path) && !is_writable($path)) {
            (new LogService())->warning('Cache file not writable: ' . $path);
            chmod($path, 0660);
        }
        @file_put_contents($path, serialize($value));
    }

    /**
     * @param string $key
     * @return string
     */
    public function getFilePath($key)
    {
        $fileName = preg_replace('/[^0-9a-z]+/i', '_', $key) . '_' . md5($key . $this->salt) . '.cache';
        return ABSPATH . $this->dir . '/' . $fileName;
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete($key)
    {
        unlink($this->getFilePath($key));
    }

    /**
     * @param string $key
     * @param $maxAge
     * @return mixed|null
     */
    public function get($key, $maxAge = 7200)
    {
        if (!$this->exists($key, $maxAge)) {
            return null;
        }
        return unserialize(file_get_contents($this->getFilePath($key)));
    }

    /**
     * @param string $key
     * @param $maxAge
     * @return bool
     */
    public function exists($key, $maxAge = 7200)
    {
        if (!$this->isActive) {
            return false;
        }
        $path = $this->getFilePath($key);
        return file_exists($path) && filemtime($path) > time() - $maxAge;
    }

}
