<?php


namespace BlackParadise\LaravelAdmin\Core\Services;


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

    public function getTypeFile(string $thumb, string $type):string
    {
        return (new StorageManager())->getDisk()->mimeType($type.'/'.$thumb);
    }

    /**
     * @return string
     */
    public function getDefaultPicture(): string
    {
        return env('APP_URL').'/bpadmin/img/default.jpg';
    }
}
