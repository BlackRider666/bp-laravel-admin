<?php


namespace BlackParadise\LaravelAdmin\Core;


class PathManager
{
    /**
     * @param $thumb
     * @param string $type
     * @return string
     */
    public function getFile($thumb, string $type): string
    {
        return (new StorageManager())->getDisk()->url($type.'/'.$thumb);
    }

    /**
     * @return string
     */
    public function getDefaultPicture(): string
    {
        return env('APP_URL').'/bpadmin/img/default.jpg';
    }
}
