<?php
/*
Plugin Name: Pagination Fu!
Description: Yet another pagination plugin.
Author: Markus Mayer
Version: 1.0
Author URI: http://blog.defx.de
License: GPL2

    Copyright 2010  Markus Mayer

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

$Id: propimgscale.php 217173 2010-03-14 02:11:04Z sunside $

*/

/**
 * An entity, describing a page
 */
class PaginationFuEntity
{
    /**
     * Creates a new instance of the class.
     * @param id int The page's id
     * @param name string The page's name
     * @param url string The page's url
     */
    public function __construct($id = NULL, $name = NULL, $url = NULL)
    {
        $this->id       = $id;
        $this->name     = $name;
        $this->url      = $url;
    }
    
    /**
     * @var Determines whether this is a gap
     */
    public $isGap = FALSE;
    
    /**
     * @var Determines whether this is a static link
     */
    public $isStatic = FALSE;
    
    /**
     * @var Determines whether this is a backlink
     */
    public $isBacklink = FALSE;
    
    /**
     * @var The type of static page
     */
    public $staticType = NULL;
    
    /**
     * @var id int The page id
     */
    public $id;
    
    /**
     * @var name string The page's name/title
     */
    public $name;  
    
    /**
     * @var url string The page's url
     */
    public $url;
    
    /**
     * @var The link relation
     */
    public $relation = NULL;
    
    /**
     * @var url boolean Determines whether this instance represents the current page
     */
    public $isCurrent = FALSE;
    
    /**
     * @var strideIndex int The stride index
     */
    public $strideIndex = 0;
}

/**
 * Renderer for page elements
 */
class PaginationFuRenderer 
{
    /**
     * @var array The arguments for the renderer
     */
    protected $arguments; 
    
    /**
     * Contructor
     * @param arguments array The arguments
     */    
    public function __construct(array &$arguments)
    {
        $this->arguments = $arguments; 
    }
    
    /**
     * Gets the rel tag
     */
    private function getRelTag($page)
    {
        if(empty($page->relation)) return NULL;
        $rel = $page->relation;
        if($this->arguments['enable_rel_prefetch'])
        {
            if(stristr($rel, 'first') !== FALSE || stristr($rel, 'prev') || stristr($rel, 'next')) $rel .= ' prefetch';
        }
        return ' rel="'.$rel.'"';
    }
    
    /**
     * Renders a page element ([1][2][3])
     * @param page PaginationFuEntity The entity to be rendered.
     * @return string A string representing the page elment
     */
    protected function renderPage($page)
    {
        $rel = $this->getRelTag($page);
        
        // backlink, yay
        $class = 'page';
        $backlink_enabled = $page->isBacklink && $this->arguments['enable_index_backlink'];
        if($backlink_enabled) $class .= ' current backlink linktoindex'; 
        
        if($page->isCurrent && !$backlink_enabled)
            $element = '<span class="page-'.$page->strideIndex.' current '.$class.'" title="'.$page->title.'">'.$page->strideIndex.'</span>';
        else
            $element = '<a class="page-'.$page->strideIndex.' '.$class.'" href="'.$page->url.'" title="'.$page->title.'"'.$rel.'>'.$page->strideIndex.'</a>';

        $tag = '<li>'.$element.'</li>';
        return $tag;   
    }
    
    /**
     * Renders a gap/ellipsis (...)
     * @return string A string representing the gap
     */
    protected function renderGap()
    {
        $tag = '<li><span class="gap">&#133;</span></li>';
        return $tag;
    }
    
    /**
     * Renders a static link
     * @param page PaginationFuEntity The entity to be rendered.
     * @param position The position of the link in the array
     * @return string A string representing the link (previous, next)
     */
    protected function renderStaticLink($page, $position)
    {
        // early exit
        if($position == 0 && !$this->arguments['always_show_navlinks']) return NULL;
        
        // get the class and name
        $class  = $page->staticType;
        if($position < 0)
        {
            $name = $this->arguments['translations']['html_left_icon'].$page->name;
        }
        elseif($position > 0)
        {
            $name = $page->name.$this->arguments['translations']['html_right_icon'];
        }  
        else 
        {
            $name = $page->name;
        }
        
        // relation
        $rel = $this->getRelTag($page);
                        
        // render
        if($page->isCurrent)
            $element = '<span class="page-'.$page->strideIndex.' current '.$class.'" title="'.$page->title.'">'.$name.'</span>';
        else
            $element = '<a class="page-'.$page->strideIndex.' '.$class.'"'.$rel.' href="'.$page->url.'" title="'.$page->title.'">'.$name.'</a>';

        $tag = '<li>'.$element.'</li>';
        return $tag; 
    }
    
    /**
     * Renders the items.
     * @param pageList array The page list
     * @return array The rendered items.
     */
    public function &render(array $pageList)
    {
        $list = array();
        $position = -1;
        foreach($pageList as $page)
        {
            // current position check.
            // keep at -1 until we hit the "current" mark,
            // afterwards switch to 1
            if($page->isCurrent) $position = 0;
            elseif($position == 0) $position = 1;
            
            if($page->isGap)
            {
                $item = $this->renderGap();    
            }
            elseif($page->isStatic)
            {
                $item = $this->renderStaticLink($page, $position);    
            }
            else 
            {
                $item = $this->renderPage($page);
            }
            
            if(empty($item)) continue;
            $list[] = $item;
        }
        
        return $list;
    }
}

/**
 * Class that enumerates the pages.
 */
class PaginationFuEnumerator
{
    /**
     * @var array The arguments for the renderer
     */
    protected $arguments; 
    
    /**
     * @var int The current page
     */
    protected $currentPage = 0;
    
    /**
     * @var int The total number of pages
     */
    protected $totalPages = 0;
    
    /**
     * Contructor
     * @param arguments array The arguments
     */    
    public function __construct(array &$arguments)
    {
        $this->arguments = $arguments; 
    }    
    
    /**
     * Gets the total number of items.
     * @return int The number of total items that will be generated.
     */
    public function getTotalItemCount()
    {
        return (2*$this->arguments['range_around_current']) + // range around current page
                $this->arguments['min_pages_at_start'] +      // pages at the start
                $this->arguments['min_pages_at_end'] +        // pages at the end
                1 +                                           // the current page
                2;                                            // ellipses
    }    
    
    /**
     * Enumerates the pages in the given range.
     * @param currentPage int The current page
     * @param pageCount int The total number of pages
     * @return array An array of PaginationFuEntity arrays or FALSE in case of an error.
     */
    public function &enumeratePages($currentPage = 0, $pageCount = 0)
    {       
        // Get the page numbers
        $data                   = $this->getCurrentPageAndPageCount($currentPage, $pageCount);              
        if(empty($data)) return FALSE;
        
        $page                   = $data['page'];
        $pages                  = $data['pages'];
        
        // get parameters for the enumeration
        $minPagesAtStart        = $this->arguments['min_pages_at_start'];
        $minPagesAtEnd          = $this->arguments['min_pages_at_end'];
        $pagesAroundCurrent     = $this->arguments['range_around_current'];
        
        // Generate left block
        $leftBlock = array (
            'start' => 1,
            'end'   => $minPagesAtStart
            );

        // Generate right block
        $rightBlockNeeded = TRUE;
        $rightBlock = array (
            'start' => $pages - $minPagesAtEnd + 1,
            'end'   => $pages
            );

        // Generate center block
        $centerBlockNeeded = TRUE;
        $centerBlock = array (
            'start' => min($page - $pagesAroundCurrent, $rightBlock['start']),
            'end'   => max($page + $pagesAroundCurrent, $leftBlock['end'])
            );

        // Difference of the left block to the center block
        $diffLeftCenter     = $centerBlock['start'] - $leftBlock['end'] - 1;

        // Merge center block with left block
        if($diffLeftCenter <= 1)
        {
            $overlap = ($leftBlock['end']+1) - $centerBlock['start'] + 1;

            // Merge ranges with overlap
            if($centerBlock['end'] > $leftBlock['end']) $leftBlock['end'] = $centerBlock['end'];
            $leftBlock['end'] += $overlap;
            $leftBlock['end'] = min($pages, $leftBlock['end']);

            // Set new ranges for further processing
            $centerBlock['start'] = $leftBlock['start'];
            $centerBlock['end'] = $leftBlock['end'];

            // Mark center block as unused
            $centerBlockNeeded = FALSE;
        }

        // Difference of the center block to the right block
        $diffRightCenter    = $rightBlock['start'] - $centerBlock['end'] - 1;

        // Merge center block with right block
        if($diffRightCenter <= 1)
        {
            $overlap = $centerBlock['end'] - ($rightBlock['start']-1) + 1;

            // Merge ranges, including the overlap
            if($centerBlock['start'] < $rightBlock['start']) $rightBlock['start'] = $centerBlock['start'];
            $rightBlock['start'] -= $overlap;
            $rightBlock['start'] = max(1, $rightBlock['start']);

            // Mark center block as unused
            $centerBlockNeeded = FALSE;
        }

        // Merge right block with left block
        if( $rightBlock['start'] <= $leftBlock['end'] + 1 ||
            $leftBlock['end'] >= $rightBlock['start'] - 1 ||
            // also test the case that there is no left item, but a gap between left and center:
            ($leftBlock['start'] == $rightBlock['start'] - 1 && $leftBlock['end'] == 0)
            )
        {
            if($rightBlock['end'] > $leftBlock['end']) $leftBlock['end'] = $rightBlock['end'];

            // since the blocks are merge, set the right block as unused
            $rightBlockNeeded = FALSE;
        }

        // Determine if there is a right ellipsis required
        $needRightEllipsis = $rightBlock['start'] > $pages;

        
        // Prepare the array
        $items = array();
        $current = $error ? 0 : $page; // prevent highlighting a page
        
        // prev page
        $previousPageId = max($page-1, 1);
        $this->addStatic($items, $previousPageId, $page, 'prev');
        
        // Render the blocks
        $this->enumerateRange($items, $leftBlock['start'], $leftBlock['end'], $current);
        if($centerBlockNeeded)
        {
            $this->addGap($items);
            $this->enumerateRange($items, $centerBlock['start'], $centerBlock['end'], $current);
        }
        if($rightBlockNeeded)
        {
            $this->addGap($items);
            $this->enumerateRange($items, $rightBlock['start'], $rightBlock['end'], $current);
        }
        
        // prev page
        $nextPageId = min($page+1, $pages);
        $this->addStatic($items, $nextPageId, $page, 'next');
        
        // revert array if necessary
        //TODO: Comment behavior
        if($this->arguments['reverse_list']) 
        {
            $items = array_reverse($items);
        }

        // for debugging purposes only
        
        /*
        $blocks = array (
            'leftBlock' => array('used' => TRUE, 'data' => $leftBlock),
            'centerBlock' => array('used' => $centerBlockNeeded, 'data' => $centerBlock),
            'rightBlock' => array('used' => $rightBlockNeeded, 'data' => $rightBlock),
            );
        var_dump($blocks);
        die("blocks");
        */

        // Return the array
        return $items;
    }
    
    /**
     * Attaches a gap to the item array
     * @param items array The item array.
     */
    private function addGap(&$items)
    {
        $gap        = new PaginationFuEntity();
        $gap->isGap = TRUE;
        $items[]    = $gap;
    }
    
    /**
     * Attaches a gap to the item array
     * @param items array The item array.
     */
    private function addStatic(&$items, $index, $currentPage, $type = 'prev')
    {
        //TODO: Comment behavior
        $name               = $type == 'prev' ? $this->arguments['translations']['html_newer'] : $this->arguments['translations']['html_older']; 
        
        $link               = &$this->generateEntity($index, FALSE);
        $link->name         = $name; 
        
        $link->isCurrent    = $index == $currentPage;
        $link->isStatic     = TRUE;
        $link->staticType   = $type;
        $link->relation     = $type == 'prev' ? 'prev' : ($type == 'next' ? 'next' : NULL);

        $items[]            = $link;
    }    
    
    /**
     * Renders a range of items (pages).
     * @param items array out The array of items to which the item will be attached.
     * @param start int The start of the range
     * @param end int The end of the range
     * @param current int The current page
     */
    private function enumerateRange(&$items, $start, $end, $current)
    {
        if($start < 1 || $end < 1 || $start > $end || $end < $start) return;
        for($i=$start; $i<=$end; ++$i)
        {
            $is_current = ($i == $current);
            $items[] = &$this->generateEntity($i, $is_current);
        }
    }
    
    /**
     * Gets the total page count and the current page
     * @param currentPage int The current page
     * @param pageCount int The total number of pages
     * @return array The page information or FALSE in case of an error
     */
    private function &getCurrentPageAndPageCount($currentPage = 0, $pageCount = 0)
    {
        global $wpdb, $wp_query;
        
        // Return the stored page numbers, if set
        if(!empty($this->currentPage) && !empty($this->totalPages)) 
        {
            return array('page' => $this->currentPage, 'pages' => $this->totalPages);
        }
        
        if(!empty($currentPage) && !empty($pageCount)) 
        {           
            // store the page numbers
            $this->currentPage      = $currentPage;
            $this->totalPages       = $pageCount;
            
            // return the array
            return array('page' => $currentPage, 'pages' => $pageCount);
        }
        $page       = 0;
        $pages      = 0;
                
        // Comment page behavior
        if($this->arguments['type'] == 'comments')
        {           
            $page = get_query_var('cpage');
        	$posts_per_page = get_option('comments_per_page');

            // correct for nested comments
            $result = $wpdb->get_results( $wpdb->prepare( "
                        		SELECT COUNT(*) AS count
                        		FROM $wpdb->comments
                        		WHERE comment_post_ID >= %d 
                                    AND comment_parent > 0 
                                    AND comment_approved > 0" ,
                        		$wp_query->post->ID ));
            $difference = $result[0]->count;
            $pages = intval(ceil(($wp_query->comment_count-$difference) / $posts_per_page));
            $page = max(min($page, $pages), 1);

            // do not render if there is only one page
            if($pages <= 1 && !$this->arguments['options']['always_show_comments_pagination']) return FALSE;
        }
        // index/archive page behavior
        elseif(is_home() || is_archive()) // || is_404()
        {                       
            // Get the current page
            $page = get_query_var('paged');
            $page = !empty($page) ? max(intval($page), 1) : 1;

            // Get the total number of pages
            $posts_per_page = max(intval(get_query_var('posts_per_page')), 1);
            $pages = max(intval(ceil($wp_query->found_posts / $posts_per_page)), 1);
            
            // adjust for maximum page
            $page = min($page, $pages);
        }
        elseif(is_single())
        {                      
            // are we coming from an archive?
            if($this->arguments['options']['enable_cat_browsing'] && !empty($wp_query->query['category_name']))
            {
                user_error("Category browsing not implemented.", E_ERROR);
                return FALSE;
                //$pages  = PaginationFuRenderer::getPageCountFromCategory();
                //$page   = PaginationFuRenderer::getPageIdFromCategory($pages);
            }
            else
            {
                //TODO: Was ist mit passwortgeschützten Seiten? Versteckten Seiten? Unveröffentlichten Seiten?
                $result = $wpdb->get_results( $wpdb->prepare( "
                            		SELECT COUNT(*) AS count
                            		FROM $wpdb->posts
                            		WHERE wp_posts.ID >= %d
                            			AND (post_type = 'post'
                            				AND post_parent = '0'
                            				AND post_status = 'publish')
                            		ORDER BY post_date DESC" ,
                            		$wp_query->post->ID ));
                $page = $result[0]->count;

                //TODO: Es gibt garantiert die Anzahl der Posts in wp_query
                $result = $wpdb->get_results("
                            		SELECT COUNT(*) AS count
                            		FROM $wpdb->posts
                            		WHERE (post_type = 'post'
                            				AND post_parent = '0'
                            				AND post_status = 'publish')
                            		ORDER BY post_date DESC");
                $pages = $result[0]->count;
            }
        }
        else
        {
            return FALSE;
        }
        
        // store the page numbers
        $this->currentPage      = $page;
        $this->totalPages       = $pages;

        // return the information
        return array('page' => $page, 'pages' => $pages);
    }     
    
    /**
     * Generates a page entity
     * @param index int The item's index
     * @param isCurrent boolean Whether this is the current item
     * @return &PaginationFuEntity The generated entity
     */
    private function &generateEntity($index, $isCurrent = FALSE)
    {      
        $pageId     = $this->translateStrideIndexToPageId($index);
        $data       = &$this->getPageData($pageId, $index);
        $url        = $this->getUrl($pageId, $index, $data);
        $name       = $this->generateName($pageId, $index, $data);
        $title      = $name;
        
        // Create the entity
        $entity = new PaginationFuEntity($pageId, $name, $url);
        $entity->title          = $title;
        $entity->isCurrent      = $isCurrent;
        $entity->strideIndex    = $index;
        $entity->relation       = $index == 1 ? "first" : 
                                    ($index == $this->totalPages ? 'last' : 
                                    ($index == $this->currentPage -1 ? 'prev' :
                                    ($index == $this->currentPage +1 ? 'next' :
                                    NULL)));
                                    
        // edges
        if($index == 1 && $index == $this->currentPage-1) $entity->relation = "first prev";
        elseif($index == $this->totalPages && $index == $this->currentPage+1) $entity->relation = "last next";
        
        // backlink?
        $entity->isBacklink     = !empty($data['index_pageid']);
        
        // Return the entity reference
        $this->cachePageData($data, $pageId);
        return $entity;   
    }
    
    /**
     * Gets information about the page with the given ID (cached)
     * @var pageId int The page ID
     * @return Page information.
     */
    private function &getPageData($pageId, $index)
    {
        $pageData = array();
        
        // Check the cache
        $cached_result = wp_cache_get( 'posts:post-'.$pageId, $this->arguments['cacheGroup'] );
        if(!empty($cached_result)) return $cached_result;
        
        // Save the page id. (it's pretty obvious, though)
        $pageData['pageId'] = $pageId;
        $pageData['strideIndex'] = $index;
        
        // cache the stride index
        wp_cache_set( 'posts:stride-'.$index, $pageId, $this->arguments['cacheGroup'] );
        
        // Cache the information and return the value
        $this->cachePageData($pageData, $pageId);
        return $pageData;
    }
    
    /**
     * Caches the page data
     * @param pageData the data to cache
     * @param pageId The pages' id
     */
    protected function cachePageData(&$pageData, $pageId = 0)
    {
        if($pageId == 0)
        {
            if(empty($pageData['id'])) return FALSE;
            $pageId = $pageData['id'];
        }
        
        // Cache the information and return the value
        wp_cache_set( 'posts:post-'.$pageId, $pageData, $this->arguments['cacheGroup'] );
        return TRUE;
    }
    
    /**
     * Translates the stride index to a page id
     * @param index int The stride index
     * @return int The page ID
     **/
    private function translateStrideIndexToPageId($index)
    {
        global $wpdb;
        
        // try to get the index from the cache
        $cached_result = wp_cache_get( 'posts:stride-'.$index, $this->arguments['cacheGroup'] );
        if(!empty($cached_result)) return $cached_result;
        
        // Get the information
        if(is_single())
        {           
            // check for category
            $category_id = $this->getCategoryId();
            $parent_category = empty($category_id) ? FALSE : $category_id;

            // Get the pages
            if($parent_category === FALSE || !$this->arguments['enable_cat_browsing'])
            {
                $result = $wpdb->get_results( $wpdb->prepare( "
                            		SELECT wp_posts.ID
                            		FROM $wpdb->posts
                            		WHERE (post_type = 'post'
                            				AND post_parent = '0'
                            				AND post_status = 'publish')
                            		ORDER BY post_date DESC
                                    LIMIT 1
                                    OFFSET %d" ,
                            		max(intval($index)-1, 0) ));
            }
            elseif($this->arguments['enable_cat_browsing'])
            {
                $result = $wpdb->get_results( $wpdb->prepare( "
                            		SELECT $wpdb->term_relationships.object_id as ID FROM $wpdb->term_relationships
                                        LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = 8
                                        LEFT JOIN $wpdb->posts ON wp_posts.ID = $wpdb->term_relationships.object_id
                                        WHERE $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
                                            AND (post_type = 'post'
                                            AND post_parent = '0'
                                            AND post_status = 'publish')
                               		ORDER BY post_date DESC
                                    LIMIT 1
                                    OFFSET %d",
                            		max(intval($index)-1, 0) ));
            }
            if(empty($result)) return FALSE;
            
            // do only the ID lookup to let WP handle the filter internals etc.
            return $result[0]->ID;
        }
        
        return $index;
    }
    
    /**
     * Generates a name for a given stride index
     * @param pageId int The page id
     * @param strideIndex int The stride index
     * @param pageData The page data
     * @return string The name
     */
    private function generateName($pageId, $strideIndex, &$pageData = NULL)
    {
        // try the cache first
        if(!empty($pageData['title'])) return $pageData['title']; 
        
        if($this->arguments['do_title_lookup'] && 
           (is_home() || is_archive()) && 
           get_query_var('posts_per_page') == 1)
        {                                  
            // query the post
            //TODO: We should find something more sophisticated here. Loading every post probably isn't the best option. 
            $query = new WP_Query();
            $query->query('showposts=1'.'&paged='.intval($strideIndex));
    
            // if there is a post, return it's title
            if(!empty($query->post))
            {
                $pageData['post'] = $query->post; // in case we need it
                $pageData['title'] = $query->post->post_title;
                return $pageData['title'];
            }
        }
        elseif(is_single())
        {
            if(!empty($pageData['backlink']))
            {
                $pageId = $pageData['index_pageid'];
                $pageData['title'] = str_ireplace('{page}', $pageId, $this->arguments['translations']['to_index_title']);
            }
            elseif(empty($pageData['title']))
            {
                $pageData['title'] = get_the_title($pageId);
            }
            
            return $pageData['title'];
        }
        
        // Use alternative title
        $key = 'alternative_title';
        if($this->arguments['type'] == 'comments') $key = 'comments_alternative_title';
        $pageData['title'] = str_ireplace('{page}', $strideIndex, $this->arguments['translations'][$key]);
        
        return $pageData['title'];
    }
    
    /**
     * Gets an URL to the given page.
     * @param pageId int The page number
     * @param strideIndex The running index in the page loop
     * @param pageData The local cache
     * @return string The URL to the page
     */
    protected function getUrl($pageId, $strideIndex, &$pageData = NULL)
    {              
        // try the cache first
        if(!empty($pageData['url'])) return $pageData['url'];
               
        // try to generate an index backlink
        $url = FALSE;
        if(is_single() && $strideIndex == $this->currentPage) 
        {
            $url = $this->generateIndexBacklink($pageId, $strideIndex, $pageData);
            $pageData['backlink'] = $url;
        }
        
        // Generate a normal url
        if($url === FALSE)
        {
            if($this->arguments['type'] == 'comments')
            {
                $url = get_comments_pagenum_link($pageId);  
            } 
            elseif(is_single()) 
            {
                $url = get_permalink($pageId);   
            }
            else 
            {
                $url = get_pagenum_link(intval($pageId));
            }
        }
        if($url !== FALSE) $pageData['url'] = $url;
        
        return $url;
    }
    
    /**
     * Generates a backlink to the index
     * @param pageId int The page number
     * @param strideIndex The running index in the page loop
     * @param pageData The local cache
     * @return string The URL to the page
     */
    private function generateIndexBacklink($pageId, $strideIndex, &$pageData = NULL)
    {
        // special treatment for single pages
        if(!is_single() || $this->arguments['type'] != 'default') return FALSE;
        
        global $wp_query;
        $pageId     = $this->getPageIdFromCategory();
        if($this->arguments['enable_cat_browsing'] && !empty($pageId))
        {           
            $cat_name = $wp_query->query['category_name'];
            $category = get_category_by_slug($cat_name);
            if(empty($category))
                $url  = trailingslashit(get_option('home')).'?category_name='.$cat_name.'&paged='.$pageId;
            else
                $url  = trailingslashit(get_option('home')).'cat='.$category->cat_ID.'&paged='.$pageId;

            // Filter the URL (i.e. for subdomain plug-ins, etc.)
            $url = apply_filters('get_pagenum_link', $url);
        }
        else
        {            
            $pageId = $this->getPageIndexFromPostIndex($strideIndex, TRUE);
            $url    = trailingslashit(get_option('home')).'?paged='.$pageId;
        }
        
        // save the page id
        $pageData['index_pageid'] = $pageId;
        
        return $url;
    }
    
    /**
     * Gets the page index from the post index
     * @var postIndex The post index
     * @return The page index
     */
    protected function getPageIndexFromPostIndex($postIndex, $forceCalculation = FALSE)
    {
        if(is_single() && !$forceCalculation) return $postIndex;

        $posts_per_page = max(intval(get_query_var('posts_per_page')), 1);
        $postIndex = max($postIndex - 1, 0);
        return intval($postIndex / $posts_per_page) + 1;
    }
    
    /**
     * Gets the category ID from the category name
     * @var category_name string The category name
     * @return The category id or FALSE in case of an error.
     */
    protected function getCategoryId($category_name = FALSE)
    {
        global $wpdb, $wp_query;

        $cat_id = get_query_var('cat');
        if(!empty($cat_id)) return $cat_id;

        if($category_name === FALSE) $category_name = $wp_query->query['category_name'];
        if(empty($category_name)) return FALSE;

        $query = "SELECT $wpdb->term_taxonomy.term_taxonomy_id as id
                    FROM $wpdb->term_taxonomy
                    LEFT JOIN $wpdb->terms
                        ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
                    WHERE $wpdb->terms.slug = %s
                    LIMIT 1;";
        $result = $wpdb->get_results( $wpdb->prepare( $query, $category_name ));

        if(empty($result)) return FALSE;
        return $result[0]->id;
    }
    
    /**
     * Gets the page index from a post ID
     * @return int|bool The page index (1 based) or FALSE in case of an error
     */
    protected function getPageIdFromPostId($postId, $postIndex = FALSE)
    {
        global $wpdb, $wp_query;

        // lookup the post index if it is not already known
        if(empty($postIndex) || intval($postIndex) < 1)
        {
            $result = $wpdb->get_results( $wpdb->prepare( "
                        		SELECT COUNT(*) AS count
                        		FROM $wpdb->posts
                        		WHERE wp_posts.ID >= %d
                        			AND (post_type = 'post'
                        				AND post_parent = '0'
                        				AND post_status = 'publish')
                        		ORDER BY post_date DESC" ,
                        		$postId ));
            if(empty($result)) return FALSE;
            $postIndex = $result[0]->count;
        }

        // return the value
        return $this->getPageIndexFromPostIndex($postIndex);
    }
    
    /**
     * Gets the post number for a given category name.
     * @var post_count The number of posts in that category
     * @var category_name string The category name
     * @return The number of items or FALSE, in case of an error.
     */
    protected function getPageIdFromCategory($post_count = FALSE, $category_name = FALSE)
    {
        global $wpdb, $wp_query;

        if($category_name === FALSE) $category_name = $wp_query->query['category_name'];
        if(empty($category_name)) return FALSE;

        // Get the number of posts
        if(empty($post_count)) $post_count = $this->getPageCountFromCategory($category_name);

        // Get the current post index
        $query = "SELECT COUNT($wpdb->term_relationships.object_id) AS count FROM $wpdb->term_relationships
                    LEFT JOIN $wpdb->terms ON $wpdb->terms.slug = %s
                    LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
                    WHERE $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
                        AND $wpdb->term_relationships.object_id >= %d
                    ORDER BY $wpdb->term_relationships.object_id DESC;";
        $result = $wpdb->get_results( $wpdb->prepare( $query, $category_name, $wp_query->post->ID ));

        if(empty($result)) return FALSE;
        $postIndex = $result[0]->count;

        // calculate the page id
        return $this->getPageIndexFromPostIndex($postIndex);
    }
    
    /**
     * Gets the number of posts for a given category name.
     * @var category_name string The category name
     * @return The number of items or FALSE, in case of an error.
     */
    protected function getPageCountFromCategory($category_name = FALSE)
    {
        global $wpdb, $wp_query;

        if($category_name === FALSE) $category_name = $wp_query->query['category_name'];
        if(empty($category_name)) return FALSE;

        $query = "SELECT COUNT($wpdb->term_relationships.object_id) AS count FROM $wpdb->term_relationships
                    LEFT JOIN $wpdb->terms ON $wpdb->terms.slug = %s
                    LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
                    WHERE $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
                    ORDER BY $wpdb->term_relationships.object_id DESC;";
        $result = $wpdb->get_results( $wpdb->prepare( $query, $category_name ));

        if(empty($result)) return FALSE;
        return $result[0]->count;
    }
}



/**
 * The main class
 */
class PaginationFuClass
{
    /**
     * @var string The plugin version.
     */
    private $version = "1.0";

    /**
     * @var array The options
     */
    protected $configuration = array();
    
    /**
     * @var string The cache key
     */
    protected $cacheGroup = 'PaginationFu';

    /**
     * @var array The default options
     */
    private $defaultConfiguration = array(
        
        // Options
        'reverse_list'                  => FALSE,
        'reverse_comments_list'         => FALSE,
        'always_show_navlinks'          => FALSE,
        'always_show_comments_pagination'
                                        => TRUE,
        'enable_index_backlink'         => TRUE,
        'enable_cat_browsing'           => FALSE,
        'do_title_lookup'               => TRUE,
        'embed_css'                     => TRUE,
        'enable_rel_prefetch'           => TRUE,

        'min_pages_at_start'            => 1,
        'min_pages_at_end'              => 1,
        'range_around_current'          => 3,         
        
        // Tags
        'tags' => array(
            'main_class'                => 'pagination-fu',
            'main_comments_class'       => 'pagination-fu pagination-fu-comments',
            'html_main_start'           => '<div class="{class}" role="navigation">',
            'html_main_end'             => '</div>',
            'html_list_start'           => '<ol class="{class}">',
            'html_list_end'             => '</ol>'
            ),
        
        // Translations
        'translations' => array(
            'html_right_icon'           => '...',
            'html_left_icon'            => '...',
            'html_older'                => '...',
            'html_newer'                => '...',
            'html_comments_older'       => '...',
            'html_comments_newer'       => '...',
            'alternative_title'         => '...',
            'comments_alternative_title'=> '...',
            'to_index_title'            => '...'
            )
        );

    /**
     * PHP4 style constructor
     */
    public function PaginationFu()
    {
        $this->__construct();
    }

    /**
     * PHP5 style constructor
     */
    public function __construct()
    {
        // Pump up the volume
        add_action('init', array(&$this, 'init'), 1000 );

        add_action('admin_menu', array(&$this, 'registerOptionsPage'), 1000 );
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filterPluginActions'), 10, 2 );
    }

    /**
     * Translates the default strings
     */
    private function initApplyTranslations()
    {
        // translate default options
        $this->defaultConfiguration['translations']['html_right_icon']      = __('&#160;&#187;', 'pagination_fu');
        $this->defaultConfiguration['translations']['html_left_icon']       = __('&#171;&#160;', 'pagination_fu');
        $this->defaultConfiguration['translations']['html_older']           = __('older', 'pagination_fu');
        $this->defaultConfiguration['translations']['html_newer']           = __('newer', 'pagination_fu');
        $this->defaultConfiguration['translations']['html_comments_older']  = __('older', 'pagination_fu');
        $this->defaultConfiguration['translations']['html_comments_newer']  = __('newer', 'pagination_fu');
        $this->defaultConfiguration['translations']['alternative_title']    = __('Page {page}', 'pagination_fu');
        $this->defaultConfiguration['translations']['comments_alternative_title']
                                                                            = __('Comment page {page}', 'pagination_fu');
        $this->defaultConfiguration['translations']['to_index_title']       = __('Back to index (page {page})', 'pagination_fu');        
    }

    /**
     * Initializes the plugin
     */
    public function init()
    {
        load_plugin_textdomain('pagination_fu');

        // Translate the default strings
        $this->initApplyTranslations();

        // load options
        $options = get_option('pagination_fu_options', $this->defaultConfiguration);
        if(!empty($options))
        {
            $this->configuration = array_merge($this->defaultConfiguration, $options);
        }
        else
        {
            $this->configuration = $this->defaultConfiguration;
        }
        
        // Add this instance to the options
        $this->configuration['PaginationFu'] = &$this;

        // embed css
        if ($this->configuration['embed_css']) add_action('wp_print_styles', array(&$this, 'embedCSS'));
    }

    /**
     * Gets the options for the calls to the render() and getRendered() functions
     * @var userOptions array Array of user options
     * @return The combined options
     */
    private function getCallArguments($userArguments = FALSE)
    {
        $defaultArguments = array(
            'type'          => 'default'
            );

        // Merge the options
        if(!empty($userArguments) && is_array($userArguments))
        {
            $userArguments = array_merge($this->configuration, array_merge($defaultArguments, $userArguments));
        }
        else {
            $userArguments = array_merge($this->configuration, $defaultArguments);
        }

        // Sanitize type
        if($userArguments['type'] != 'default' && $userArguments['type'] != 'comments') $userArguments['type'] = 'default';

        // Generate cache key
        if($userArguments['type'] == 'comments') $userArguments['cacheKey'] = 'paginationFu-comments';
        else $userArguments['cacheKey'] = 'paginationFu';
        $userArguments['cacheGroup'] = $this->cacheGroup;

        // Return the options
        return $userArguments;
    }

    /**
     * Loads the CSS stylesheet
     */
    public function embedCSS()
    {
        $file = WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/pagination-fu.css';

        // load user specific template, if it exists
        if (false !== @file_exists(TEMPLATEPATH . "/pagination-fu.css")) {
            $file = get_template_directory_uri() . "/pagination-fu.css";
        }

        // enqeue the css file
        wp_enqueue_style('pagination-fu', $file, FALSE, $this->version, 'screen');
    }

    /**
     * Outputs the rendered pagination
     */
    public function render($args = FALSE)
    {
        echo $this->getRendered($args);
    }

    /**
     * Renders the pagination
     */
    public function getRendered($args = FALSE)
    {
        // Unterscheidung zwischen:
        //  Index
        //  Single
        //  Archiv
        //  Kommentar

        // Get call arguments
        $args       = $this->getCallArguments($args);


        // Get some values
        $type       = $args['type'];
        $cacheKey   = $args['cacheKey'];

        // Check cache
        $cached_result = wp_cache_get( $cacheKey, $this->cacheGroup );
        if(!empty($cached_result)) return $cached_result;

        // check for comments mode and leave if necessary
        if($type == 'comments' && !get_option('page_comments')) return FALSE;
        
        if($type == 'comments') return "Kommentar-Rendern ist aus Testgründen deaktiviert.";

        // Generate the content
        $enumerator = new PaginationFuEnumerator($args);
        $pages      = $enumerator->enumeratePages();
        if(empty($pages)) return FALSE;
        
        // Render the content
        $renderer   = new PaginationFuRenderer($args);
        $list       = $renderer->render($pages);
        if(empty($list)) return FALSE;
        
        // Create the list
        $class    = $this->configuration['tags']['main_class'];
        if($type == 'comments') $class = $this->configuration['tags']['main_comments_class'];
        $content  = str_ireplace('{class}', $class, $this->configuration['tags']['html_main_start']);
        $content .= str_ireplace('{class}', $class, $this->configuration['tags']['html_list_start']);
        $content .= implode('', $list);
        $content .= $this->options['html_list_end'];
        $content .= $this->options['html_main_end']."\n";

        // Apply filters and return
        $filtered_content = apply_filters('pagination_fu', $content);

        // add to chache
        wp_cache_set( $cacheKey, $filtered_content, $this->cacheGroup );
        return $filtered_content;
    }


    /**
    * Adds a settings link to the plugin page
    */
    public function filterPluginActions($links, $file)
    {
        $settings_link = '<a href="options-general.php?page=' . plugin_basename(__FILE__) . '">' . __('Settings', 'propimgscale') . '</a>';
        array_unshift($links, $settings_link); // before other links
        return $links;
    }

    /**
    * Registers the options page
    */
    public function registerOptionsPage()
    {
        if ( function_exists('add_options_page') )
        {
            add_options_page(__('Pagination Fu! settings', 'pagination_fu'), __('Pagination Fu!', 'pagination_fu'), 8, __FILE__, array(&$this, 'renderOptionsPage'));
        }
    }

    /**
    * Renders the options page
    */
    public function renderOptionsPage()
    {
        $options = get_option('pagination_fu_options');

        if ( isset($_POST['Submit']) ) {
            check_admin_referer('paginationfu-update-options');
            //$options['width'] = max(0, (int)$_POST['width']);
            //$options['imgclass'] = $_POST['imgclass'];
            //$options['imgexclass'] = $_POST['imgexclass'];
            update_option('pagination_fu_options', $options);
            echo '<div id="message" class="updated fade"><p><strong>' . __('Settings saved.', 'pagination_fu') . '</strong></p></div>';
        }

    ?>
        <div class="wrap">
            <div class="icon32" id="icon-options-general"><br/></div>
            <h2><?php _e('Pagination Fu!', 'pagination_fu') ?></h2>
            <form class="form-table" action="" method="post" id="pagination_fu" accept-charset="utf-8">
                <?php wp_nonce_field('paginationfu-update-options'); ?>
                <p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'pagination_fu') ?>"/></p>
            </form>
        </div>
    <?php
    }

}

$PaginationFu = new PaginationFuClass();

if(!function_exists('get_PaginationFu')) {
    /**
     * Gets the rendered pagination
     */
    function get_PaginationFu($options = FALSE)
    {
        global $PaginationFu;
        return $PaginationFu->getRendered($options);
    }
}

if(!function_exists('PaginationFu')) {
    /**
     * Renders the pagination
     */
    function PaginationFu($options = FALSE)
    {
        global $PaginationFu;
        return $PaginationFu->render($options);
    }
}

if(!function_exists('get_PaginationFuComments')) {
    /**
     * Gets the rendered pagination
     */
    function get_PaginationFuComments()
    {
        global $PaginationFu;
        return $PaginationFu->getRendered(array('type' => 'comments'));
    }
}

if(!function_exists('PaginationFuComments')) {
    /**
     * Renders the pagination
     */
    function PaginationFuComments()
    {
        global $PaginationFu;
        return $PaginationFu->render(array('type' => 'comments'));
    }
}