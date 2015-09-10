<?php

/**
 * SSFCoreMAP.php - Restroutes for the StudIP Client plugin News
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Philipp Danner <philipp@danner-web.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     ExtendedNews
 */
require_once 'lib/object.inc.php';
class SSFCoreMap extends RESTAPI\RouteMap {
	
	// ************************************************************************//
	// Routes for file download core plugin
	// ************************************************************************//
	
	/**
	 * This route returns a tree of all semesters with leafs: file and nodes: structure.
	 *
	 * @get /studip-client-core/documenttree/
	 */
	public function getDocumenttreeAll() {
		$output = array ();
		foreach ( Semester::findBySQL ( "JOIN seminare v JOIN seminar_user USING (Seminar_id) WHERE user_id=? AND start_time <= ende AND start_time >=beginn GROUP BY semester_id", array (
				$GLOBALS ['user']->id 
		) ) as $semester ) {
			$output [] = $this->getDocumenttree ( $semester->id );
		}
		return $output;
	}
	
	/**
	 * This route sets the document as downloaded.
	 *
	 * @put /studip-client-core/document/:document_id
	 */
	public function putDocuments($document_id = null) {
		if ($document_id != null) {
			object_set_visit ( $document_id, 'documents', $GLOBALS ['user']->id );
			$this->status ( 200 );
		} else {
			$this->status ( 500 );
		}
		$this->body ( null );
	}
	
	// ************************************************************************//
	// Routes for news core plugin
	// ************************************************************************//
	
	/**
	 * Returns unread news of the current user as json object
	 *
	 * valid ranges are: studip (system wide), institute, courses
	 *
	 * @get /ssf-core/news/:range
	 */
	public function getNews($range = null) {
		$output = array ();
		
		$newslist = null;
		$type = null;
		
		switch ($range) {
			case 'studip' :
				$newslist = StudipNews::findBySQL ( "JOIN news_range r USING (news_id) LEFT JOIN object_user_visits o ON (news_id = o.object_id) WHERE o.object_id IS NULL AND r.range_id = ? GROUP BY news_id", array (
						'studip' 
				) );
				// $newslist = StudipNews::GetNewsByRange ( 'studip', true, true );
				$type = 'studip';
				break;
			case 'institute' :
				$newslist = StudipNews::findBySQL ( "JOIN news_range r USING (news_id) JOIN user_inst i ON (r.range_id = i.Institut_id) LEFT JOIN object_user_visits o ON (news_id = o.object_id) WHERE o.object_id IS NULL AND i.user_id = ? GROUP BY news_id", array (
						$GLOBALS ['user']->id 
				) );
				$type = 'studip';
				break;
			case 'courses' :
				$newslist = StudipNews::findBySQL ( "JOIN news_range r USING (news_id) JOIN seminare ON(Seminar_id = range_id) JOIN seminar_user s USING(Seminar_id) LEFT JOIN object_user_visits o ON (news_id = o.object_id) WHERE o.object_id IS NULL AND s.user_id = ? GROUP BY news_id", array (
						$GLOBALS ['user']->id 
				) );
				$type = 'courses';
				break;
		}
		
		if ($newslist == null) {
			return $output;
		}
		
		foreach ( $newslist as $news ) {
			$result = array (
					"news_id" => $news->id,
					"topic" => $news->topic,
					"body" => formatReady ( $news->body ),
					"date" => $news->date,
					"author" => $news->author,
					"chdate" => $news->chdate,
					"mkdate" => $news->mkdate,
					"expire" => $news->expire,
					"allow_comments" => $news->allow_comments,
					"chdate_uid" => $news->chdate_uid 
			);
			$output [] = $result;
		}
		return $output;
	}
	
	/**
	 * Sets the news of the current user with the given $news_id to readed (visisted)
	 *
	 * @put /ssf-core/news/:news_id
	 */
	public function putNews($news_id = null) {
		if ($news_id != null) {
			object_set_visit ( $news_id, 'news', $GLOBALS ['user']->id );
		}
		$this->status ( 200 );
		$this->body ( null );
	}
	
	// ************************************************************************//
	// private functions for documenttree
	// ************************************************************************//
	private function getDocumenttree($semesterId = null) {
		$semester = Semester::find ( $semesterId );
	
		$result = array (
				"semester_id" => $semester->id,
				"title" => $semester->name,
				"description" => $semester->description,
				"begin" => $semester->beginn,
				"end" => $semester->ende,
				"seminars_begin" => $semester->vorles_beginn,
				"seminars_end" => $semester->vorles_ende
		);
		$result["courses"]= $this->getCourses ( $semester->id );
	
		return $result;
	}
	private function getCourses($semester_id = null) {
		$output = array ();
		
		foreach ( Course::findBySQL ( "JOIN seminar_user USING (Seminar_id) WHERE user_id = ?", array (
				$GLOBALS ['user']->id 
		) ) as $course ) {
			if ($course->start_semester->id == $semester_id) {
				$result = array (
						"course_id" => $course->id,
						"course_nr" => $course->VeranstaltungsNummer,
						"title" => $course->name,
						"subtitle" => $course->untertitel,
						"description" => $course->beschreibung,
						"folders" => $this->getFolders ( $course->id ) 
				);
				$output [] = $result;
			}
		}
		
		return $output;
	}
	private function getFolders($course_id = null) {
		$output = array ();
		
		// Allgemeine Ordner
		$general_folders = DocumentFolder::findBySQL ( "seminar_id = ? AND range_id = seminar_id", array (
				$course_id 
		) );
		
		// new top folder
		$new_top_folders = DocumentFolder::findBySQL ( "seminar_id = ? AND range_id = MD5(CONCAT(?, 'top_folder'))", array (
				$course_id,
				$course_id 
		) );
		
		// statusgruppen Ordner
		$statusgruppen_folders = DocumentFolder::findBySQL ( "JOIN statusgruppe_user ON (statusgruppe_id = range_id) WHERE seminar_id = ? AND statusgruppe_user.user_id = ?", array (
				$course_id,
				$GLOBALS ['user']->id 
		) );
		
		// themen folder
		$themen_folders = DocumentFolder::findBySQL ( "JOIN themen ON (issue_id = range_id) WHERE range_id = issue_id AND folder.seminar_id = ?", array (
				$course_id 
		) );
		
		$folders = array_merge_recursive ( $general_folders, $statusgruppen_folders, $themen_folders, $new_top_folders );
		
		foreach ( $folders as $folder ) {
			
			$result = array (
					"folder_id" => $folder->id,
					"user_id" => $folder->user_id,
					"name" => $folder->name,
					"mkdate" => $folder->mkdate,
					"chdate" => $folder->chdate,
					"description" => $folder->description ?  : '',
					"permissions" => $folder->permission,
					"subfolders" => $this->getSubFolders ( $folder->id ),
					"files" => $this->getDocuments ( $folder->id ) 
			);
			
			$permissions = array ();
			foreach ( array (
					1 => 'visible',
					'writable',
					'readable',
					'extendable' 
			) as $bit => $perm ) {
				if ($folder->permission & $bit) {
					$permissions [$perm] = true;
				} else {
					$permissions [$perm] = false;
				}
			}
			$result ['permissions'] = $permissions;
			$output [] = $result;
		}
		
		return $output;
	}
	private function getSubFolders($folder_id = null) {
		$output = array ();
		
		foreach ( DocumentFolder::findBySQL ( "range_id = ?", array (
				$folder_id 
		) ) as $folder ) {
			
			$result = array (
					"folder_id" => $folder->id,
					"user_id" => $folder->user_id,
					"name" => $folder->name,
					"mkdate" => $folder->mkdate,
					"chdate" => $folder->chdate,
					"description" => $folder->description ?  : '',
					"permissions" => $folder->permission,
					"subfolders" => $this->getSubFolders ( $folder->id ),
					"files" => $this->getDocuments ( $folder->id ) 
			);
			
			$permissions = array ();
			foreach ( array (
					1 => 'visible',
					'writable',
					'readable',
					'extendable' 
			) as $bit => $perm ) {
				if ($folder->permission & $bit) {
					$permissions [$perm] = true;
				} else {
					$permissions [$perm] = false;
				}
			}
			$result ['permissions'] = $permissions;
			$output [] = $result;
		}
		
		return $output;
	}
	private function getDocuments($folder_id = null) {
		$output = array ();
		
		foreach ( StudipDocument::findByRange_id ( $folder_id ) as $file ) {
			$result = array (
					"document_id" => $file->id,
					"user_id" => $file->user_id,
					"name" => $file->name,
					"description" => $file->description ?  : '',
					"mkdate" => $file->mkdate,
					"chdate" => $file->chdate,
					"filename" => $file->filename,
					"filesize" => $file->filesize,
					"mime_type" => get_mime_type ( $file->filename ),
					"protected" => ($file->protected === '0' ? false : true) 
			);
			$output [] = $result;
		}
		
		return $output;
	}
}
