<?php


namespace BlackParadise\LaravelAdmin\Core;


use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class StorageManager
{
    public function __construct(string $disk = 'public')
    {
        $this->disk = $disk;
    }

    /** @var string */
    protected $disk = 'public';

    /**
     * @return Filesystem
     */
    public function getDisk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * @param UploadedFile $file
     * @param string $type
     * @param int $size
     * @return string
     */
    public function savePicture(UploadedFile $file, string $type, int $size): string
    {
        $image = Image::make($file)->resize($size,null, function ($constraint) {
            $constraint->aspectRatio();
        })->save();
        $filename = uniqid(time(), true) . '.' . $file->getClientOriginalExtension();
        if (!$this->getDisk()->exists($type)) {
            $this->getDisk()->makeDirectory($type);
        }
        $this->getDisk()->put($type . '/' . $filename, $image);

        return $filename;
    }

    /**
     * @param string $file
     * @param string $type
     */
    public function deleteFile(string $file, string $type): void
    {
        $this->getDisk()->delete($type.'/'.$file);
    }

    /**
     * @param string $avatar
     * @return string
     */
    public function savePictureFromUrl(string $avatar): string
    {
        $file = file_get_contents($avatar);
        $filename = uniqid(time(), true) . '.jpg';
        if (!$this->getDisk()->exists('user_avatar')) {
            $this->getDisk()->makeDirectory('user_avatar');
        }
        $this->getDisk()->put('user_avatar/' . $filename, $file);
        return $filename;
    }

    /**
     * @param UploadedFile $file
     * @param string $type
     * @return string
     */
    public function saveFile(UploadedFile $file, string $type): string
    {
        $filename = uniqid(time(), true) . '.' . $file->getClientOriginalExtension();
        if (!$this->getDisk()->exists($type)) {
            $this->getDisk()->makeDirectory($type);
        }
        $this->getDisk()->putFileAs(
            $type , $file, $filename
        );

        return $filename;
    }
}
