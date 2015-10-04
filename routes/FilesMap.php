<?php

/**
 * FilesMap.php - Restroutes for the StudIP Client core plugin FileDownloader
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Philipp Danner <philipp@danner-web.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     StudipClient
 */
require_once 'lib/object.inc.php';
class FilesMap extends RESTAPI\RouteMap {
		
	/**
	 * This route returns a tree of all semesters with leafs: file and nodes: structure.
	 *
	 * @get /studip-client-core/documenttree/
	 */
	public function getDocumenttreeAll() {
		return Semester::findAndMapBySQL ( function ($semester) {
			return $this->getDocumenttree ( $semester->id );
		}, "JOIN seminare v ON (start_time <= ende AND start_time >=beginn) JOIN seminar_user USING (Seminar_id) WHERE user_id=? GROUP BY semester_id", array (
				$GLOBALS ['user']->id 
		) );
	}
	
	/**
	 * This route returns a tree of all semesters with leafs: file and nodes: structure.
	 *
	 * @get /studip-client-core/documenttree/:semester_id
	 */
	public function getDocumenttreeSingle($semester_id) {
		return $output [] = $this->getDocumenttree ( $semester->id );
	}
	
	/**
	 * This route sets the document as downloaded.
	 *
	 * @put /studip-client-core/visited_document/:document_id
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
	// private functions for documenttree
	// ************************************************************************//
	private function getDocumenttree($semesterId = null) {
		$semester = Semester::find ( $semesterId );
		$result = array (
				"semester_id" => $semester->id,
				"title" => $semester->name,
				"description" => $semester->description 
		);
		$result ["courses"] = $this->getCourses ( $semester->id );
		
		return $result;
	}
	
	private function getCourses($semester_id = null) {
		$course_to_json = function ($course) use($semester_id) {
			if ($course->start_semester->id == $semester_id) {
				return array (
						"course_id" => $course->id,
						"course_nr" => $course->VeranstaltungsNummer,
						"title" => $course->name,
						"folders" => $this->getFolders ( $course->id ) 
				);
			}
		};
		$result = Course::findAndMapBySQL ( $course_to_json, "JOIN seminar_user USING (Seminar_id) WHERE user_id = ?", array (
				$GLOBALS ['user']->id 
		) );
		
		$output = array ();
		foreach ( $result as $item ) {
			if ($item != null)
				$output[] = $item;
		}
		return $output;
	}

	private function getFolders($course_id = null) {
		$folder_to_json = function ($folder) {
			$result = array (
					"folder_id" => $folder->id,
					"name" => $folder->name,
					"mkdate" => $folder->mkdate,
					"chdate" => $folder->chdate,
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
			
			return $result;
		};
		$general_folders = DocumentFolder::findAndMapBySQL ( $folder_to_json, "seminar_id = ? AND range_id = seminar_id", array (
				$course_id 
		) );
		$new_top_folders = DocumentFolder::findAndMapBySQL ( $folder_to_json, "seminar_id = ? AND range_id = MD5(CONCAT(?, 'top_folder'))", array (
				$course_id,
				$course_id 
		) );
		$statusgruppen_folders = DocumentFolder::findAndMapBySQL ( $folder_to_json, "JOIN statusgruppe_user ON (statusgruppe_id = range_id) WHERE seminar_id = ? AND statusgruppe_user.user_id = ?", array (
				$course_id,
				$GLOBALS ['user']->id 
		) );
		$themen_folders = DocumentFolder::findAndMapBySQL ( $folder_to_json, "JOIN themen ON (issue_id = range_id) WHERE range_id = issue_id AND folder.seminar_id = ?", array (
				$course_id 
		) );
		return array_merge_recursive ( $general_folders, $statusgruppen_folders, $themen_folders, $new_top_folders );
	}

	private function getSubFolders($folder_id = null) {
		$folder_to_json = function ($folder) {
			$result = array (
					"folder_id" => $folder->id,
					"name" => $folder->name,
					"mkdate" => $folder->mkdate,
					"chdate" => $folder->chdate,
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
			
			return $result;
		};
		return DocumentFolder::findAndMapBySQL ( $folder_to_json, "range_id = ?", array (
				$folder_id 
		) );
	}

	private function getDocuments($folder_id = null) {
		$document_to_json = function ($file) {
			return array (
					"document_id" => $file->id,
					"name" => $file->name,
					"mkdate" => $file->mkdate,
					"chdate" => $file->chdate,
					"filename" => $file->filename,
					"filesize" => $file->filesize,
					"protection" => $file->protected,
					"mime_type" => get_mime_type ( $file->filename ) 
			);
		};
		return StudipDocument::findAndMapBySQL ( $document_to_json, "range_id = ?", array (
				$folder_id 
		) );
	}
}
