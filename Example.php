<?php

namespace Sunnysideup\DoubleUps\Tasks;

use SilverStripe\CMS\Model\SiteTree;


use SilverStripe\Dev\BuildTask;

use SilverStripe\ORM\DB;

class RemoveDoubles extends BuildTask
{
    protected $title = 'Remove double-ups';

    protected $description = 'Goes through selected lists and checks for doubles';

    protected $enabled = true;

    private static $segment = 'test';

    protected $classes = [
        // add them here
    ];


    public function run($request)
    {
        foreach($this->classes as $className) {
            $objects = $className::get();
            $skip = [];
            foreach($objects as $object) {
                if(isset($skip[$object->ID])) {
                    DB::alteration_message('Skipping: '.$object->Title . ' - '.$object->ID);
                    continue;
                }
                DB::alteration_message('Checking: '.$object->Title . ' - '.$object->ID);
                $filter = [
                    'Title' => $object->Title
                ];
                $exclude = [
                    'ID' => $object->ID
                ];
                $other = $className::get()->filter($filter)->exclude($exclude)->first();
                if($other) {
                    DB::alteration_message(' - Found Double: '.$other->Title . ' - '.$other->ID);
                    $main = $object;
                    $delete = $other;
                    if($main->ID > $other->ID) {
                        $main = $other;
                        $delete = $main;
                    }
                    $rels =
                        $main->stat('has_many') +
                        $main->stat('belongs_many_many') +
                        $main->stat('many_many');
                    foreach($rels as $relName => $relType) {
                        DB::alteration_message(' - - Moving: '.$relName);
                        foreach($delete->$relName() as $relObject) {
                            $main->$relName()->add($relObject->ID);
                            $delete->$relName()->remove($relObject);
                        }
                    }
                    DB::alteration_message(' - Deleting: '.$delete->ID);
                    $skip[$delete->ID] = $delete->ID;
                    $delete->delete();
                }
            }
        }
    }
}
