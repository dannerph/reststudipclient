<?php

/**
 * NewsMap.php - Restroutes for the StudIP Client plugin News
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Florian Bieringer <florian.bieringer@uni-passau.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     ExtendedNews
 */
class SSFCoreMap extends RESTAPI\RouteMap {

    //************************************************************************//
    // Routes for file download core plugin
    //************************************************************************//

    /**
     * Cheks for changes of Document after timestamp and the current users
     * courses. Return either an empty json or a tree over semester (root node),
     * courses, folders, subfolders [...], Metadata of documents changed after
     * timestamp
     *
     * example tree:
     * {
     *   "semester_id" : null,
     *   "title" : null,
     *   "description" : null,
     *   "begin" : 0,
     *   "end" : 0,
     *   "seminars_begin" : 0,
     *   "seminars_end" : 0,
     *   "courses" : [ {
     *     "folders" : [ {
     *       "folder_id" : null,
     *       "user_id" : null,
     *       "name" : null,
     *       "mkdate" : 0,
     *       "chdate" : 0,
     *       "description" : null,
     *       "permissions" : null,
     *       "subfolders" : [ ],
     *       "files" : [ {
     *         "document_id" : null,
     *         "user_id" : null,
     *         "name" : null,
     *         "description" : null,
     *         "mkdate" : null,
     *         "chdate" : null,
     *         "filename" : null,
     *         "filesize" : null,
     *         "mime_type" : null,
     *         "protected" : null
     *       } ]
     *     } ],
     *     "course_id" : null,
     *     "course_nr" : null,
     *     "institute_name" : null,
     *     "title" : null,
     *     "subtitle" : null,
     *     "description" : null,
     *     "color" : null,
     *     "type" : 0
     *   } ]
     * }
     *
     * @get /ssf-core/documenttree/
     * @get /ssf-core/documenttree/:timestamp
     */
    public function getDocumentTree($timestamp = null) {
        if($timestamp === null){
            // return whole tree with root of the current semester
        }
        foreach (Course::findBySQL("JOIN seminar_user USING (Seminar_id) WHERE user_id = ?", array($GLOBALS['user']->id)) as $course) {
            $result = array(
"course_id" => $course->id,
"course_nr" => $course->VeranstaltungsNummer,
"title" => $course->name
                );
            foreach (DocumentFolder::findBySeminar_id($course->id) as $folder) {
                $result['folders'][] = $this->parseFolder($folder);
            }
            $output[$course->start_semester->id][] = $result;
        }
        return $output;
    }

    private function parseFolder($folder) {

$result['folder_id'] = $folder->id; 


//parse subfolders
        foreach (DocumentFolder::findByRange_id($folder->id) as $folder) {
$result['subfolders'][] = $this->parseFolder($folder);
        }

        foreach (StudipDocument::findByRange_id($folder->id) as $file) {
$result['files'][] = array("document_id" => $file->id);
        }
        return $result;
    }
    
    //************************************************************************//
    // Routes for news core plugin
    //************************************************************************//
    
    /**
     * Returns unread news of the current user
     * return value: array(id, topic, body, author (name), chdate, mkdate, 
     * expire, ...)
     *
     * @get /ssf-core/unreadnews
     */
    public function getUnreadNews() {
        return $output;
    }
    
    /**
     * Sets the news of the current user with the given new id $id to readed
     *
     * @put /ssf-core/setnewsreaded/:id
     */
    public function setNewsReaded($id = null) {
        
    }

}
