<?php namespace Pixel\Services\Image;

use Illuminate\Contracts\Filesystem\Cloud;
use Pixel\Contracts\Image\ImageContract as ImageContract;
use Pixel\Contracts\Image\Repository as RepositoryContract;
use Illuminate\Contracts\Filesystem\Filesystem;
use Carbon\Carbon;
use Pixel\Contracts\Image\Repository;
use Pixel\Exceptions\Image\UnsupportedFilesystemException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ColorThief\ColorThief;
use SplFileObject;

/**
 * Class ImageService
 * @package Pixel\Services\Image
 */
abstract class ImageService implements ImageContract {

    /**
     * @var ImageRepository
     */
    protected $imageRepo;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Constructor
     *
     * @param RepositoryContract $imageRepo
     * @param Filesystem         $filesystem
     */
    public function __construct(RepositoryContract $imageRepo, Filesystem $filesystem)
    {
        $this->imageRepo  = $imageRepo;
        $this->filesystem = $filesystem;
    }

    /**
     * Determine if an image exists
     *
     * @param string $sid
     *
     * @return boolean
     */
    public function exists($sid)
    {
        if ( $this->imageRepo->getBySid($sid) )
            return true;

        return false;
    }

    /**
     * Retrieve an image
     *
     * @param string $sid
     *
     * @return Repository
     */
    public function get($sid)
    {
        return $this->imageRepo->getBySid($sid);
    }

    /**
     * Retrieve an image by its primary key
     *
     * @param int $id
     *
     * @return mixed
     */
    public function getById($id)
    {
        return $this->imageRepo->getById($id);
    }

    /**
     * Retrieve images posted by the specified user
     *
     * @param $user
     *
     * @return mixed
     */
    public function getByUser($user)
    {
        //
    }

    /**
     * Retrieve images posted to the specified album
     *
     * @param $album
     *
     * @return mixed
     */
    public function getByAlbum($album)
    {
        //
    }

    /**
     * Retrieve recently posted images
     *
     * @param int  $page
     * @param int  $perPage
     * @param bool $withExpired
     * @param bool $withInvisible
     *
     * @return mixed
     */
    public function recent($page = 1, $perPage = 12, $withExpired = false, $withInvisible = false)
    {
        return $this->imageRepo->recent($page, $perPage, $withExpired, $withInvisible);
    }

    /**
     * Save a new image
     *
     * @param UploadedFile $file
     * @param array        $params
     *
     * @return mixed
     */
    public function create(UploadedFile $file, array $params = [])
    {
        $imageData     = $this->getImageData($file);
        $dominantColor = $this->getImageDominantColor( $file->getRealPath() );

        // Set some additional fields
        $params['sid']             = str_random('7');
        $params['delete_key']      = str_random('40');
        $params['original_width']  = $imageData['width'];
        $params['original_height'] = $imageData['height'];
        $params['upload_ip']       = \Request::getClientIp();
        $params['upload_uagent']   = \Request::server('HTTP_USER_AGENT');

        // Concatenate our parameters
        $params = array_merge($imageData, $dominantColor, $params);

        // Create the image in our repository
        $image = $this->imageRepo->create($params);

        # Images are saved in YYYY/MM/DD sub-directories with md5sum's as file names.
        # This is useful in multiple ways, it avoids wasting storage space by saving multiple
        # copies of the same file and keeps an organized and human readable filesystem heirarchy.

        // Make sure our filesystem path exists
        $path = $image->getBasePath();
        if ( ! $this->filesystem->exists($path) )
            $this->filesystem->makeDirectory($path);

        // Get the file contents and save them to our configured filesystem
        $filename     = "{$image->md5sum}.{$image->type}";
        $fileContents = file_get_contents( $file->getRealPath() );
        $this->filesystem->put($path.$filename, $fileContents);

        // Create the scaled (preview/thumbnail) versions of our new image
        $this->createScaledImages($image);

        // Return our image collection
        return $image;
    }

    /**
     * Crop an image to the specified dimensions
     *
     * @param $image
     * @param $params
     *
     * @return mixed
     */
    abstract public function crop($image, $params);

    /**
     * Convert an image to the specified filetype
     *
     * @param $image
     * @param $filetype
     *
     * @return mixed
     */
    abstract public function convert($image, $filetype);

    /**
     * Delete an image
     *
     * @param Repository|int $image
     *
     * @return bool
     */
    public function delete($image)
    {
        // Make sure $image is a Repository instance
        if ( ! $image instanceof Repository)
            $image = $this->get($image);

        // Set our filesystem paths
        $paths['original']  = $image->getRealPath($image::ORIGINAL);
        $paths['preview']   = $image->getRealPath($image::PREVIEW);
        $paths['thumbnail'] = $image->getRealPath($image::THUMBNAIL);

        // Loop through our paths and delete the files
        foreach ($paths as $path) {
            if ( $this->filesystem->exists($path) )
                $this->filesystem->delete($path);
        }

        // Delete the image from our backend
        $image->delete($image->id);

        // Return success
        return true;
    }

    /**
     * Get the dominant color used in an image
     *
     * @param string $filePath
     *
     * @return array|bool
     */
    public function getImageDominantColor($filePath)
    {
        // Set up an instance of Color Thief
        $colorThief = new ColorThief();
        $pallet = $colorThief->getColor($filePath, 8);

        // Successful result, return the color pallet
        if ( isset($pallet[0], $pallet[1], $pallet[2]) ) {
            return [
                'red'   => $pallet[0],
                'green' => $pallet[1],
                'blue'  => $pallet[2]
            ];
        }

        // Failed to get a dominant color, return null
        return [
            'red'   => null,
            'green' => null,
            'blue'  => null
        ];
    }

    /**
     * Generate a download response for the specified image
     *
     * @param Repository  $image
     * @param string|null $scale
     * @param string      $disposition
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
     * @throws UnsupportedFilesystemException
     */
    public function downloadResponse(Repository $image, $scale = null, $disposition = 'inline')
    {
        $fileSystem = config('filesystems.default');
        // @todo: Sendfile response

        // Standard filesystem response
        if ( $fileSystem == 'local' ) {
            $filePath   = config('filesystems.disks.local.root').'/'.$image->getRealPath($scale);
            $fileObject = new SplFileObject($filePath);
            return response()->download($fileObject, $image->name, [], $disposition);
        }

        // Amazon S3 response
        if ( $fileSystem == 's3' ) {
            // Generate a URL to the image on our S3 bucket
            $bucket   = config('filesystems.disks.s3.bucket');
            $filePath = $image->getRealPath($scale);
            $imageUrl = "https://s3.amazonaws.com/{$bucket}/{$filePath}";

            // Return a temporary redirect
            return response()->redirectTo($imageUrl);
        }

        throw new UnsupportedFilesystemException("{$fileSystem} is not a supported Filesystem");
    }

    /**
     * Create scaled versions of an image resource
     *
     * @param Repository $image
     *
     * @return mixed
     */
    abstract public function createScaledImages(Repository $image);

    /**
     * Get information about an uploaded image file
     *
     * @param UploadedFile $file
     *
     * @return array
     */
    abstract protected function getImageData(UploadedFile $file);

}