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
    
    /**
     * This route returns an array of semesters of current user.
     *
     *	TODO: only semesters of the user, not all
     *
     * @get /ssf-core/semesters/
     */
    public function getSemesters() {
    	
    	$output = array();
    	
    	foreach ( Semester::getAll() as $semester ) {
			$result = array (
					"semester_id" => $semester->id,
					"title" => $semester->name,
					"description" => $semester->description,
					"begin" => $semester->beginn,
					"end" => $semester->ende,
					"seminars_begin" => $semester->vorles_beginn,
					"seminars_end" => $semester->vorles_ende
			);
			$output [] = $result;
		}
    	
    	return $output;
    }
    
    /**
     * This route returns an array of courses of the specified semester.
     *
     * @get /ssf-core/courses/:semester_id
     */
    public function getCourses($semester_id = null) {
    	
    	$output = array();
    	
		foreach ( Course::findBySQL ( "JOIN seminar_user USING (Seminar_id) WHERE user_id = ?", array (
				$GLOBALS ['user']->id 
		) ) as $course ) {
			if ($course->start_semester->id == $semester_id) {
				$result = array (
						"course_id" => $course->id,
						"course_nr" => $course->VeranstaltungsNummer,
						"title" => $course->name,
						"subtitle" => $course->untertitel,
						"description" => $course->beschreibung 
				);
				$output [] = $result;
			}
		}
		
		return $output;
	}
    
    /**
     * This route returns an array of folders of the specified course.
     *
     * TODO: AND range_id = seminar_id not correct, only for "Allgemeiner Ordner"
     *
     * @get /ssf-core/folders/:course_id
     */
    public function getFolders($course_id = null) {
    	
    	$output = array();
    	
    	// Allgemeine Ordner
    	$general_folders = DocumentFolder::findBySQL ("seminar_id = ? AND range_id = seminar_id", array (
    			$course_id));
    	
    	// TODO: new top folder, do some magic?
    	$new_top_folders = DocumentFolder::findBySQL ("seminar_id = ? AND range_id IN (?, MD5(CONCAT(?, 'top_folder')))", array (
    			$course_id, "76ed43ef286fb55cf9e41beadb484a9f","76ed43ef286fb55cf9e41beadb484a9f"));
    	
    	//return md5('ad8dc6a6162fb0fe022af4a62a15e309'.'top_folder');
    	
    	// statusgruppen Ordner
    	$statusgruppen_folders = DocumentFolder::findBySQL ("JOIN statusgruppe_user ON (statusgruppe_id = range_id) WHERE seminar_id = ? AND statusgruppe_user.user_id = ?", array (
				$course_id,$GLOBALS ['user']->id));
    	
		// themen folder
    	$themen_folders = DocumentFolder::findBySQL ("JOIN themen ON (issue_id = range_id) WHERE range_id = issue_id AND folder.seminar_id = ?", array (
    			$course_id));
    	
    	$folders = array_merge_recursive($general_folders, $statusgruppen_folders, $themen_folders, $new_top_folders);
    	
    	
    	foreach ( $folders as $folder ) {
			
			$result = array (
					"folder_id" => $folder->id,
					"user_id" => $folder->user_id,
					"name" => $folder->name,
					"mkdate" => $folder->mkdate,
					"chdate" => $folder->chdate,
					"description" => $folder->description?:'',
					"permissions" => $folder->permission
			);
			
			$permissions = array();
			foreach (array(1=>'visible', 'writable', 'readable', 'extendable') as $bit => $perm) {
				if ($folder->permission & $bit) {
					$permissions [$perm] = true;
				} else {
					$permissions [$perm] = false;
				}
			}
			$result['permissions'] = $permissions;
			$output [] = $result;
		}
    	
    	return $output;
    }
    
    /**
     * This route returns an array of subfolders of the specified folder.
     *
     * @get /ssf-core/subfolders/:folder_id
     */
    public function getSubFolders($folder_id = null) {

    	$output = array();
    	
    	foreach ( DocumentFolder::findBySQL ( "range_id = ?", array (
				$folder_id
		) ) as $folder ) {
			
			$result = array (
					"folder_id" => $folder->id,
					"user_id" => $folder->user_id,
					"name" => $folder->name,
					"mkdate" => $folder->mkdate,
					"chdate" => $folder->chdate,
					"description" => $folder->description?:'',
					"permissions" => $folder->permission
			);
			
			$permissions = array();
			foreach (array(1=>'visible', 'writable', 'readable', 'extendable') as $bit => $perm) {
				if ($folder->permission & $bit) {
					$permissions [$perm] = true;
				} else {
					$permissions [$perm] = false;
				}
			}
			$result['permissions'] = $permissions;
			$output [] = $result;
		}
    	
    	return $output;
    }
    
    /**
     * This route returns an array of documents (only meta data) of the specified folder.
     *
     * @get /ssf-core/documents/:folder_id
     */
    public function getDocuments($folder_id = null) {
    	
    	$output = array();
    	
    	foreach (StudipDocument::findByRange_id($folder_id) as $file) {
			$result = array (
					"document_id" => $file->id,
     				"user_id" => $file->user_id,
     				"name" => $file->name,
     				"description" => $file->description?:'',
     				"mkdate" => $file->mkdate,
     				"chdate" => $file->chdate,
     				"filename" => $file->filename,
     				"filesize" => $file->filesize,
    				"mime_type" => get_mime_type($file->filename),
     				"protected" => ($file->protected === '0' ? false : true)
			);
			$output [] = $result;
		}
    	
    	return $output;
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
