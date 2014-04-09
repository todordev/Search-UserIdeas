<?php
/**
 * @package      UserIdeas
 * @subpackage   Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2014 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

jimport("userideas.init");

class plgSearchUserIdeas extends JPlugin {

    protected $autoloadLanguage = true;

	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.5
	 */
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
	}

	/**
	 * @return array An array of search areas
	 */
	public function onContentSearchAreas() {
		static $areas = array(
			'projects' => 'PLG_SEARCH_USERIDEAS_IDEAS'
		);
		return $areas;
	}

	/**
	 * UserIdeas Search method.
	 *
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav.
     *
	 * @param string $text Target search string
	 * @param string $phrase Matching option, exact|any|all
	 * @param string $ordering Ordering option, newest|oldest|popular|alpha|category
	 * @param mixed  $areas An array if the search it to be restricted to areas, null if search all
     *
     * @return array()
	 */
	public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null) {
	    
		if (is_array($areas)) {
			if (!array_intersect($areas, array_keys($this->onContentSearchAreas()))) {
				return array();
			}
		} 
		
		$limit	        = $this->params->def('search_limit',	20);
		
		$text = JString::trim($text);
		if ($text == '') {
			return array();
		}

		$return = $this->searchItems($text, $phrase, $ordering, $limit);
		
		return $return;
	}
	
	/**
	 * 
	 * Search phrase in items.
	 * 
	 * @param string  $text
	 * @param string  $phrase
	 * @param string  $ordering
	 * @param integer $limit
     *
     * @return array
	 */
	private function searchItems($text, $phrase, $ordering, $limit) {
	    
	    $db		    = JFactory::getDbo();
	    $searchText = $text;
	    $wheres	    = array();
	    $rows       = array();
	    
		switch ($phrase){
		    
			case 'exact':
				$text		= $db->quote('%'.$db->escape($text, true).'%', false);
				$wheres[]	= 'a.title LIKE '.$text;
				$where		= '(' . implode(') OR (', $wheres) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words	= explode(' ', $text);
				foreach ($words as $word) {
					$word		= $db->quote('%'.$db->escape($word, true).'%', false);
					$wheres[]	= 'a.title LIKE '.$word;
					$wheres[]	= implode(' OR ', $wheres);
				}
				$where	= '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				break;
		}

		switch ($ordering) {
		    
			case 'oldest':
				$order = 'a.record_date ASC';
				break;

			case 'popular':
				$order = 'a.hits DESC';
				break;

			case 'alpha':
				$order = 'a.title ASC';
				break;

			case 'category':
				$order = 'c.title ASC';
				break;

			case 'newest':
			default:
				$order = 'a.record_date DESC';
				
		}

		$return = array();
		
		$query	= $db->getQuery(true);

		if($limit > 0) {
		    
		    $query->clear();
    		
    		//sqlsrv changes
    		$case_when = ' CASE WHEN ';
    		$case_when .= $query->charLength('a.alias');
    		$case_when .= ' THEN ';
    		$a_id = $query->castAsChar('a.id');
    		$case_when .= $query->concatenate(array($a_id, 'a.alias'), ':');
    		$case_when .= ' ELSE ';
    		$case_when .= $a_id.' END as slug';
    		
    		$case_when2 = ' CASE WHEN ';
    		$case_when2 .= $query->charLength('c.alias');
    		$case_when2 .= ' THEN ';
    		$c_id = $query->castAsChar('c.id');
    		$case_when2 .= $query->concatenate(array($c_id, 'c.alias'), ':');
    		$case_when2 .= ' ELSE ';
    		$case_when2 .= $c_id.' END as catslug';
    		
    		// Select
    		$query->select('a.title, a.description AS text, a.record_date AS created');
    		$query->select('c.title as section, 2 AS browsernav, '.$case_when .','. $case_when2);
    		
    		// FROM and JOIN
    		$query->from('#__uideas_items AS a');
    		$query->innerJoin('#__categories AS c ON a.catid = c.id');
    		
    		// WHERE
    		$query->where("a.published = 1");
    		$query->where($where);
    		
    		// ORDER
    		$query->order($order);
    		
    		
    	    $db->setQuery($query, 0, $limit);
    		$rows     = $db->loadObjectList();

		}
		
		if ($rows) {
		    
			foreach($rows as $key => $row) {
				$rows[$key]->href       = UserIdeasHelperRoute::getDetailsRoute($row->slug, $row->catslug);
				$rows[$key]->title      = strip_tags($rows[$key]->title);
				$rows[$key]->text       = strip_tags($rows[$key]->text);
			}

			foreach($rows as $item) {
				if (searchHelper::checkNoHTML($item, $searchText, array('title', 'text'))) {
					$return[] = $item;
				}
			}
		}
		
		return $return;
	}
	
}
