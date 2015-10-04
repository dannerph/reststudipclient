<?php

/**
 * NewsMap.php - Restroutes for the StudIP Client core plugin News
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
class NewsMap extends RESTAPI\RouteMap {
	
	/**
	 * Returns unread news of the current user as json object
	 *
	 * valid ranges are: studip (system wide), institute, courses
	 *
	 * @get /studip-client-core/news/:range
	 */
	public function getNews($range = null) {
		$news_to_json = function ($news) {
			return array (
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
		};
		
		switch ($range) {
			case 'studip' :
				return StudipNews::findAndMapBySQL ( $news_to_json, "JOIN news_range r USING (news_id) LEFT JOIN object_user_visits o ON (news_id = o.object_id) WHERE o.object_id IS NULL AND r.range_id = ? GROUP BY news_id", array (
						'studip' 
				) );
			case 'institute' :
				return StudipNews::findAndMapBySQL ( $news_to_json, "JOIN news_range r USING (news_id) JOIN user_inst i ON (r.range_id = i.Institut_id) LEFT JOIN object_user_visits o ON (news_id = o.object_id) WHERE o.object_id IS NULL AND i.user_id = ? GROUP BY news_id", array (
						$GLOBALS ['user']->id 
				) );
			case 'courses' :
				return StudipNews::findAndMapBySQL ( $news_to_json, "JOIN news_range r USING (news_id) JOIN seminare ON(Seminar_id = range_id) JOIN seminar_user s USING(Seminar_id) LEFT JOIN object_user_visits o ON (news_id = o.object_id) WHERE o.object_id IS NULL AND s.user_id = ? GROUP BY news_id", array (
						$GLOBALS ['user']->id 
				) );
		}
	}
	
	/**
	 * Sets the news of the current user with the given $news_id to readed (visisted)
	 *
	 * @put /studip-client-core/visited_news/:news_id
	 */
	public function putNews($news_id = null) {
		if ($news_id != null) {
			object_set_visit ( $news_id, 'news', $GLOBALS ['user']->id );
		}
		$this->status ( 200 );
		$this->body ( null );
	}
}
