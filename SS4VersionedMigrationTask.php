<?php

namespace App\Web;

use CWP\AgencyExtensions\Model\CarouselItem;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Widgets\Model\Widget;
use SilverStripe\Widgets\Model\WidgetArea;

/**
 * SS4 introduces versioning to a number of DataObjects. Where instances of these data objects existed in SS3,
 * these no longer appear on an upgraded site since the item only exists as Draft, or rather there is not a
 * corresponding record for it within the '_Live' table. This task goes through each DataObject table where versioning
 * has been added and publishes each instance so that a version is created.
 */
class SS4VersionedMigrationTask extends BuildTask
{

    private static $segment = 'SS4VersionedMigrationTask';

    protected static $versioned_tables = [
        'CarouselItem'  => CarouselItem::class,
        'WidgetArea'    => WidgetArea::class,
        'Widget'        => Widget::class,
    ];

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        set_time_limit(60000);
        $this->publishVersionedDataObjects();
    }

    /**
     * Loop through versioned tables that need migrating
     */
    public function publishVersionedDataObjects()
    {
        foreach (self::$versioned_tables as $tableName => $class) {
            $this->publishTableRecords(static::$versioned_tables[$tableName]);
        }
    }

    /**
     * Go through instances in a table and publish if a live version doesn't already exist
     * @param $class
     */
    public function publishTableRecords($class)
    {
        foreach ($class::get() as $record) {
            if (!$record->isLiveVersion()) {
                echo sprintf("Publishing %s %d\r\n", $record->ClassName, $record->ID);
                $record->writeToStage(Versioned::DRAFT);
                $record->doPublish(Versioned::DRAFT, Versioned::LIVE);
            }
        }
    }
}
