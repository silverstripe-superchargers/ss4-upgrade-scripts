<?php

namespace App\Web;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLUpdate;

/**
 * SS4 and its File Migration Task changes the way in which files are stored in the assets folder, with files placed
 * in subfolders named with partial hashmap values of the file version. This build task goes through the HTML content
 * fields looking for instances of image links, and corrects the link path to what it should be, with an image shortcode.
 */
class SS4ContentImageMigration extends BuildTask
{
    private static $segment = 'SS4ContentImageMigration';

    protected static $tables_with_content = [
        'SiteTree_Live',
        'SiteTree',
        'SiteTree_Versions',
    ];

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        set_time_limit(60000);
        foreach (self::$tables_with_content as $updateTable) {
            $results = DB::query('SELECT * FROM ' . $updateTable . ' WHERE Content LIKE \'%<img %\'');
            $this->processResults($results, $updateTable);
        }
    }

    /**
     * Takes a set of query results and updates image urls within a page's content.
     * @param $results
     * @param $updateTable
     */
    public function processResults($results, $updateTable)
    {
        foreach ($results as $row) {
            $content = $row['Content'];
            $images = $this->getAllImagesForUpdating($content);

            // First go through images and work out what the new tag should be
            foreach ($images as $imgTag=> $newImage) {
                // Get Img src
                $srcFull = explode('src="', $imgTag);
                $src = explode('"', $srcFull[1]);
                $srcFull = $src[0];
                $filePath = explode('/', $srcFull);
                $fileName = $filePath[count($filePath) - 1];

                // Search for a File object containing this filename
                $file = File::get()->filter('Filename:PartialMatch', $fileName)->first();

                if ($file) {

                    // Create new image source based on a file hashcode
                    $newSrc = '';

                    for ($i = 0; $i < count($filePath) - 1; $i++) {
                        $newSrc .= $filePath[$i] . '/';
                    }

                    // Only include the filehash subfolder in the path if not a resampled image
                    if (strpos($srcFull, '_resampled') == false) {
                        $hashShort = substr($file->FileHash, 0, 10);
                        $newSrc .= $hashShort . '/';
                    }

                    $newSrc .= $fileName;

                    $imageProperties = $this->getImageAttributes($imgTag);

                    /* Build up the image shortcode
                       e.g. [image src="/assets/Uploads/f92c6af6c8/Screen-Shot-2019-03-18-at-10.04.18-AM.png"
                        id="134" width="3342" height="1808" class="leftAlone ss-htmleditorfield-file image"
                        title="Screen Shot 2019 03 18 at 10.04.18 AM"] */
                    $imageStringNew = '[image src="'.$newSrc.'"'
                        . (isset($imageProperties['width']) ? ' width="' . $imageProperties['width'] . '"' : '')
                        . (isset($imageProperties['height']) ? ' height="' . $imageProperties['height'] . '"' : '')
                        . (isset($imageProperties['class']) ? ' class="' . $imageProperties['class'] . '"' : '')
                        . (isset($imageProperties['alt']) ? ' alt="' . $imageProperties['alt'] . '"' : '')
                        . (isset($imageProperties['title']) ? ' title="' . $imageProperties['title'] . '"' : '')
                        . ' id="'. $file->ID . '"]';

                    $images[$imgTag] = $imageStringNew;
                } else {
                    echo 'Link with no file found:' . $srcFull .'<br>';
                }
            }

            // Now go through the images and make the replacement in the content
            foreach ($images as $image => $newImage) {
                $content = str_replace($image, $newImage, $content);
            }

            echo 'Updating page with ID ' . $row['ID'] . '<br/>';
            $updateSQL = SQLUpdate::create($updateTable)->addWhere(['"ID"' => $row['ID']]);
            $updateSQL->addAssignments(['"Content"' => $content]);
            $updateSQL->execute();
        }
    }

    /**
     * Get all images within some page content and return as array.
     * @param $content
     * @return array
     */
    public function getAllImagesForUpdating($content)
    {
        $images = [];
        preg_match_all('/<img.*?src\s*=.*?>/', $content, $matches, PREG_SET_ORDER);
        if ($matches) {
            foreach($matches as $match) {
                $images[$match[0]] = null;
            }
        }

        return $images;
    }

    /**
     * Extracts an array of attributes from an image tag.
     * @param $imgTag
     * @return array
     */
    public function getImageAttributes($imgTag)
    {
        $attributes = [
            'title' => $this->getImageAttribute($imgTag, 'title'),
            'height' => $this->getImageAttribute($imgTag, 'height'),
            'width' => $this->getImageAttribute($imgTag,'width'),
            'class' => $this->getImageAttribute($imgTag, 'class'),
            'alt' => $this->getImageAttribute ($imgTag,'alt'),
        ];

        return $attributes;
    }

    /**
     * Gets the value of a given attribute from a tag.
     * @param $imgTag
     * @param $imageAttribute
     * @return null
     */
    public function getImageAttribute($imgTag, $imageAttribute)
    {
        $imagePropertyValue = null;
        $needle = $imageAttribute . '="';
        if (strpos($imgTag, $needle)) {
            $imagePropertyValue = explode('"', explode($needle, $imgTag)[1])[0];
        }

        return $imagePropertyValue;
    }
}
