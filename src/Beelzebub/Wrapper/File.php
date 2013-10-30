<?php
/**
 * This class is part of Beelzebub
 */

namespace Beelzebub\Wrapper;


/**
 * File class to allow mocking
 *
 * @package Beelzebub\Wrapper
 */
class File
{

    /**
     * @var string
     */
    protected $path;

    /**
     * Create new file wrapper
     *
     * @param string $file
     */
    public function __construct($file)
    {
        $this->path = $file;
    }

    /**
     * Returns whether file exists (and is file)
     *
     * @return bool
     */
    public function exists()
    {
        return file_exists($this->path) && is_file($this->path);
    }

    /**
     * Get/set file contents
     *
     * @param string $contents
     *
     * @return string
     */
    public function contents($contents = null)
    {
        if ($contents) {
            file_put_contents($this->path, $contents);

            return $contents;
        } elseif ($this->exists($this->path)) {
            return file_get_contents($this->path);
        } else {
            return null;
        }
    }

    /**
     * Remove file
     */
    public function remove()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * File path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }


}