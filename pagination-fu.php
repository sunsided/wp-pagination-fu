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
 * A renderer for the items.
 */
class PaginationFuRenderer
{
    /**
     * Renders the item.
     * @param value (mixed) The value to render. Might be a page number or a text.
     * @param page int The target page
     * @param is_current bool If set to true, this item represents the current page (e.g. should be considered disabled).
     */
    function render($value, $page, $is_current = FALSE)
    {
        user_error("Please define me", E_ERROR);
    }

    /**
     * Gets an URL to the given page.
     * @param page int The page number
     * @return string The URL to the page
     */
    function getUrl($page, $args)
    {
        if($args['type'] == 'comments') return get_comments_pagenum_link($page);
        return get_pagenum_link(intval($page));
    }

    /**
     * Gets the link title from the specified page number.
     * @param page int The page number.
     * @return The page title or the page number, if no title could be generated.
     */
    function getTitleFromPage($page, $args, $default = FALSE)
    {
        global $PaginationFu;
        $defaultReturnValue = empty($default) ? $page : $default;

        $alt_title = ($args['type'] == 'comments') ? $args['options']['comments_alternative_title'] : $args['options']['alternative_title'];
        $defaultReturnValue = str_ireplace('{page}', $defaultReturnValue, $alt_title);
        if(!$PaginationFu->options['do_title_lookup'] || $args['type'] == 'comments') return $defaultReturnValue;

        // Check the post count
        $posts_per_page = max(intval(get_query_var('posts_per_page')), 1);
        if($posts_per_page > 1) return $defaultReturnValue;

        // query the post
        $query = new WP_Query();
        $query->query('showposts=1'.'&paged='.intval($page));

        // if there is a post, return it's title
        if(!empty($query->post))
        {
            return $query->post->post_title;
        }

        // return the default
        return $defaultReturnValue;
    }

    /**
     * Translates a page index to a page number, post index, etc.
     */
    function lookupPageData($page, $args, $defaultTitle = FALSE, $defaultURL = FALSE)
    {
        $resultArray = array(
            'id'        => $page,
            'title'     => empty($defaultTitle) ? $page : $defaultTitle,
            'url'       => $defaultURL);

        // if we are on the index page
        if($args['type'] == 'comments')
        {
            // get the page url
            $resultArray['url']     = $this->getUrl($page, $args);

            // try to get the title from the page number
            $title = PaginationFuRenderer::getTitleFromPage($page, $args);
            if(!empty($title)) $resultArray['title'] = $title;
        }
        elseif(is_home() || is_archive())
        {
            // get the page url
            $resultArray['url']     = $this->getUrl($page, $args);

            // try to get the title from the page number
            $title = $this->getTitleFromPage($page, $args);
            if(!empty($title)) $resultArray['title'] = $title;
        }
        // if we are on a post page
        elseif(is_single())
        {
            global $wpdb, $PaginationFu;

            // check for category
            $category_id = PaginationFuRenderer::getCategoryId();
            $parent_category = empty($category_id) ? FALSE : $category_id;

            // Get the pages
            if($parent_category === FALSE || !$args['options']['enable_cat_browsing'])
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
                            		max(intval($page)-1, 0) ));
            }
            elseif($args['options']['enable_cat_browsing'])
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
                            		max(intval($page)-1, 0) ));
            }
            if(empty($result)) return FALSE;

            // do only the ID lookup to let WP handle the filter internals etc.
            $resultArray['id'] = $result[0]->ID;
            $resultArray['title'] = get_the_title($result[0]->ID);
            $resultArray['url'] = get_permalink($result[0]->ID);
        }

        // return the result
        return $resultArray;
    }

    /**
     * Gets the page index from the post index
     * @var postIndex The post index
     * @return The page index
     */
    function getPageIndexFromPostIndex($postIndex)
    {
        if(is_single()) return $postIndex;

        $posts_per_page = max(intval(get_query_var('posts_per_page')), 1);
        $postIndex = max($postIndex - 1, 0);
        return intval($postIndex / $posts_per_page) + 1;
    }

    /**
     * Gets the page index from a post ID
     * @return int|bool The page index (1 based) or FALSE in case of an error
     */
    function getPageLinkFromPostId($postId, $postIndex = FALSE)
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
        return PaginationFuRenderer::getPageIndexFromPostIndex($postIndex);
    }

    /**
     * Gets the post number for a given category name.
     * @var post_count The number of posts in that category
     * @var category_name string The category name
     * @return The number of items or FALSE, in case of an error.
     */
    function getPageIdFromCategory($post_count = FALSE, $category_name = FALSE)
    {
        global $wpdb, $wp_query;

        if($category_name === FALSE) $category_name = $wp_query->query['category_name'];
        if(empty($category_name)) return FALSE;

        // Get the number of posts
        if(empty($post_count)) $post_count = PaginationFuRenderer::getPageCountFromCategory($category_name);

        // Get the current post index
        $query = "SELECT COUNT($wpdb->term_relationships.object_id) AS count FROM $wpdb->term_relationships
                    LEFT JOIN $wpdb->terms ON $wpdb->terms.slug = %s
                    LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
                    WHERE $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
                        AND $wpdb->term_relationships.object_id >= %d
                    ORDER BY wp_term_relationships.object_id DESC;";
        $result = $wpdb->get_results( $wpdb->prepare( $query, $category_name, $wp_query->post->ID ));

        if(empty($result)) return FALSE;
        $postIndex = $result[0]->count;

        // calculate the page id
        return PaginationFuRenderer::getPageIndexFromPostIndex($postIndex);
    }

    /**
     * Gets the number of posts for a given category name.
     * @var category_name string The category name
     * @return The number of items or FALSE, in case of an error.
     */
    function getPageCountFromCategory($category_name = FALSE)
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

    /**
     * Gets the category ID from the category name
     * @var category_name string The category name
     * @return The category id or FALSE in case of an error.
     */
    function getCategoryId($category_name = FALSE)
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
}

/**
 * A renderer for the page items.
 */
class PaginationFuPageRenderer extends PaginationFuRenderer
{
    /**
     * @var The opening tag for an active page link
     */
    var $openTagActive      = '<a class="page page-{page}{additional_classes}" href="{url}" title="{title}">';

    /**
     * @var The closing tag for an active page link
     */
    var $closeTagActive     = '</a>';

    /**
     * @var The opening tag for the current page
     */
    var $openTagCurrent      = '<span class="page page-{page} current{additional_classes}" title="{title}">';

    /**
     * @var The closing tag for the current page
     */
    var $closeTagCurrent     = '</span>';

    /**
     * Renders the item.
     * @param value (mixed) The value to render. Might be a page number or a text.
     * @param page int The target page
     * @param is_current bool If set to true, this item represents the current page (e.g. should be considered disabled).
     */
    function render($value, $page, $is_current, $args)
    {
        $data = $this->lookupPageData($page, $args);

        $url          = $data['url'];
        $title        = $data['title'];

        $openTag      = !$is_current ? $this->openTagActive : $this->openTagCurrent;
        $closeTag     = !$is_current ? $this->closeTagActive : $this->closeTagCurrent;
        $additionalClasses = '';

        // special treatment for single pages
        if(is_single() && $args['type'] == 'default')
        {
            global $PaginationFu, $wp_query;
            if($is_current)
            {
                $pageId     = PaginationFuRenderer::getPageIdFromCategory();
                if($args['options']['enable_cat_browsing'] && !empty($pageId))
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
                    $pageId = PaginationFuRenderer::getPageLinkFromPostId(0, $page);
                    $url    = trailingslashit(get_option('home')).'?paged='.$pageId;
                }

                $openTag    = $this->openTagActive;
                $closeTag   = $this->closeTagActive;

                $title      = str_ireplace('{page}', $pageId, $PaginationFu->options['to_index_title']);

                $additionalClasses = ' current linktoindex';
            }
        }

        $searchArray  = array('{url}', '{title}', '{page}', '{additional_classes}');
        $replacements = array( $url,    $title,    $page,    $additionalClasses);

        $openTag      = str_ireplace($searchArray, $replacements, $openTag);
        $closeTag     = str_ireplace($searchArray, $replacements, $closeTag);

        return          $openTag . $value . $closeTag;
    }
}

/**
 * A renderer for the page items.
 */
class PaginationFuLinkRenderer extends PaginationFuRenderer
{
    /**
     * @var The opening tag for an active page link
     */
    var $openTagActive      = '<a class="{class} page-{page}" href="{url}" title="{title}">';

    /**
     * @var The closing tag for an active page link
     */
    var $closeTagActive     = '</a>';

    /**
     * @var The opening tag for the current page
     */
    var $openTagCurrent      = '<span class="{class} page-{page} current" title="{title}">';

    /**
     * @var The closing tag for the current page
     */
    var $closeTagCurrent     = '</span>';

    /**
     * Renders the item.
     * @param value (mixed) The value to render. Might be a page number or a text.
     * @param page int The target page
     * @param class string The class to apply
     * @param is_current bool If set to true, this item represents the current page (e.g. should be considered disabled).
     */
    function render($value, $page, $class, $is_current, $args)
    {
        $data = $this->lookupPageData($page, $args);
        $url          = $data['url'];

        $title = $value;
        if(!empty($data['title'])) $title = $data['title'];

        $openTag      = !$is_current ? $this->openTagActive : $this->openTagCurrent;
        $closeTag     = !$is_current ? $this->closeTagActive : $this->closeTagCurrent;

        $searchArray  = array('{url}', '{title}', '{page}', '{class}');
        $replacements = array( $url,    $title,    $page,    $class);

        $openTag      = str_ireplace($searchArray, $replacements, $openTag);
        $closeTag     = str_ireplace($searchArray, $replacements, $closeTag);

        return          $openTag . $value . $closeTag;
    }
}

/**
 * A renderer for the ellipsis items.
 */
class PaginationFuEllipsisRenderer extends PaginationFuRenderer
{
    /**
     * @var The opening tag for the item
     */
    var $openTag      = '<span class="gap">';

    /**
     * @var The actual ellipsis
     */
    var $ellipsisTag  = '&#133;';

    /**
     * @var The closing tag for the item
     */
    var $closeTag     = '</span>';

    /**
     * Renders the item.
     * @param value (mixed) The value to render. Might be a page number or a text.
     * @param page int The target page
     * @param is_current bool If set to true, this item represents the current page (e.g. should be considered disabled).
     */
    function render($unused = FALSE, $unused2 = FALSE, $unused3 = FALSE)
    {
        return $this->openTag . $this->ellipsisTag . $this->closeTag;
    }
}

/**
 * Enumerator for the pages.
 * Generates the page list.
 */
class PaginationFuEnumerator
{
    /**
     * @var int The number of pages around the current page.
     */
    var $pagesAroundCurrent = 3;

    /**
     * @var int The number of pages at the start
     */
    var $minPagesAtStart = 1;

    /**
     * @var int The number of pages at the end
     */
    var $minPagesAtEnd = 1;

    /**
     * Gets the total number of items.
     * @return int The number of total items that will be generated.
     */
    function getTotalItemCount()
    {
        return (2*$this->pagesAroundCurrent) +    // range around current page
                $this->minPagesAtStart +          // pages at the start
                $this->minPagesAtEnd +            // pages at the end
                1 +                               // the current page
                2;                                // ellipses
    }

    /**
     * Renders the page list as an array.
     * @param page int The current page number
     * @param pages int The count of all pages.
     * @return array The array of items.
     */
    function renderItems($page, $pages, $args)
    {
        // Generate left block
        $leftBlock = array (
            'start' => 1,
            'end'   => $this->minPagesAtStart
            );

        // Generate right block
        $rightBlockNeeded = TRUE;
        $rightBlock = array (
            'start' => $pages - $this->minPagesAtEnd + 1,
            'end'   => $pages
            );

        // Generate center block
        $centerBlockNeeded = TRUE;
        $centerBlock = array (
            'start' => min($page - $this->pagesAroundCurrent, $rightBlock['start']),
            'end'   => max($page + $this->pagesAroundCurrent, $leftBlock['end'])
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

        // Render the blocks
        $items = array();
        $this->renderRange($items, $leftBlock['start'], $leftBlock['end'], $page, $args);
        if($centerBlockNeeded)
        {
            $this->renderEllipsis($items);
            $this->renderRange($items, $centerBlock['start'], $centerBlock['end'], $page, $args);
        }
        if($rightBlockNeeded)
        {
            $this->renderEllipsis($items);
            $this->renderRange($items, $rightBlock['start'], $rightBlock['end'], $page, $args);
        }
        if($needRightEllipsis) $this->renderEllipsis($items);

        // Return the array
        return $items;
    }

    /**
     * Calls the renderer to render an ellipsis item
     * @param items array The array of items to which the item will be attached.
     */
    function renderEllipsis(&$items)
    {
        global $PaginationFu;
        $items[] = $PaginationFu->rendererEllipsis->render(FALSE, FALSE, FALSE);
    }

    /**
     * Renders a range of items (pages).
     * @param items array The array of items to which the item will be attached.
     */
    function renderRange(&$items, $start, $end, $current, $args)
    {
        global $PaginationFu;
        if($start < 1 || $end < 1 || $start > $end || $end < $start) return;
        for($i=$start; $i<=$end; ++$i)
        {
            $is_current = ($i == $current);
            $items[] = $PaginationFu->rendererPage->render($i, $i, $is_current, $args);
        }
    }
}

if (!class_exists('PaginationFuClass')) {

/**
 * The main class
 */
class PaginationFuClass
{
    /**
     * @var string The plugin version.
     */
    var $version = "1.0";

    /**
     * @var array The options
     */
    var $options = array();

    /**
     * @var array The default options
     */
    var $defaultOptions = array(
        'main_class'                => 'pagination-fu',
        'main_comments_class'       => 'pagination-fu pagination-fu-comments',
        'html_main_start'           => '<div class="{class}" role="navigation">',
        'html_main_end'             => '</div>',
        'html_list_start'           => '<ol class="{class}">',
        'html_list_end'             => '</ol>',
        'reverse_list'              => FALSE,
        'reverse_comments_list'     => FALSE,
        'html_right_icon'           => '&#160;&#187;',
        'html_left_icon'            => '&#171;&#160;',
        'html_older'                => 'older',
        'html_newer'                => 'newer',
        'html_comments_older'       => 'older',
        'html_comments_newer'       => 'newer',
        'always_show_navlinks'      => FALSE,
        'always_show_comments_pagination'
                                    => FALSE,
        'enable_cat_browsing'       => FALSE,
        'do_title_lookup'           => TRUE,
        'alternative_title'         => 'Page {page}',
        'comments_alternative_title'=> 'Comment page {page}',
        'to_index_title'            => 'Back to index (page {page})',

        'embed_css'                 => TRUE,
                        );

    /**
     * @var PaginationFuEnumerator The enumerator object.
     */
    var $enumerator;

    /**
     * @var PaginationFuRenderer Renderer for navigation links.
     */
    var $rendererLinks;

    /**
     * @var PaginationFuRenderer Renderer for the ellipsis item.
     */
    var $rendererEllipsis;

    /**
     * @var PaginationFuRenderer Renderer for the page item.
     */
    var $rendererPage;

    /**
     * PHP4 style constructor
     */
    function PaginationFu()
    {
        $this->__construct();
    }

    /**
     * PHP5 style constructor
     */
    function __construct()
    {
        // Pump up the volume
        add_action('init', array(&$this, 'init'), 1000 );

        add_action('admin_menu', array(&$this, 'registerOptionsPage'), 1000 );
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filterPluginActions'), 10, 2 );
    }

    /**
     * Initializes the plugin
     */
    function init()
    {
        load_plugin_textdomain('pagination_fu');

        // translate default options
        $this->defaultOptions['html_older']         = __('older', 'pagination_fu');
        $this->defaultOptions['html_newer']         = __('newer', 'pagination_fu');
        $this->defaultOptions['html_comments_older']
                                                    = __('older', 'pagination_fu');
        $this->defaultOptions['html_comments_newer']
                                                    = __('newer', 'pagination_fu');
        $this->defaultOptions['alternative_title']  = __('Page {page}', 'pagination_fu');
        $this->defaultOptions['comments_alternative_title']
                                                    = __('Comment page {page}', 'pagination_fu');
        $this->defaultOptions['to_index_title']     = __('Back to index (page {page})', 'pagination_fu');

        // load options
        $options = get_option('pagination_fu_options', $defaultOptions);
        if(!empty($options))
        {
            $this->options = array_merge($this->defaultOptions, $options);
        }
        else
        {
            $this->options = $this->defaultOptions;
        }

        // embed css
        if ($this->options['embed_css']) add_action('wp_print_styles', array(&$this, 'embedCSS'));

        // Create a new enumerator
        $this->enumerator       = new PaginationFuEnumerator();

        // create default renderers
        $this->createDefaultRenderers();
    }

    /**
     * Gets the options for the calls to the render() and getRendered() functions
     * @var userOptions array Array of user options
     * @return The combined options
     */
    function getCallOptions($userOptions = FALSE)
    {
        $defaultOptions = array(
            'type'      => 'default',
            'cacheKey'  => 'paginationFu'
            );

        // Merge the options
        if(empty($userOptions) || !is_array($userOptions)) return $defaultOptions;
        $userOptions = array_merge($defaultOptions, $userOptions);

        // Merge class options
        if(!empty($userOptions['options']))
        {
            $userOptions['options'] = array_merge($this->options, $userOptions['options']);
        }
        else
        {
            $userOptions['options'] = $this->options;
        }

        // Sanitize type
        if($userOptions['type'] != 'default' && $userOptions['type'] != 'comments') $userOptions['type'] = 'default';

        // Generate cache key
        if($userOptions['type'] == 'comments') $userOptions['cacheKey'] = 'paginationFu-comments';

        // Return the options
        return $userOptions;
    }

    /**
     * Creates the default renderers
     */

    function createDefaultRenderers()
    {
        $this->rendererLinks    = new PaginationFuLinkRenderer();
        $this->rendererEllipsis = new PaginationFuEllipsisRenderer();
        $this->rendererPage     = new PaginationFuPageRenderer();
    }

    /**
     * Loads the CSS stylesheet
     */
    function embedCSS()
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
    function render($args = FALSE)
    {
        echo $this->getRendered($args);
    }

    /**
     * Renders the pagination
     */
    function getRendered($args = FALSE)
    {
        // Unterscheidung zwischen:
        //  Index
        //  Single
        //  Archiv
        //  Kommentar

        // Get call arguments
        $args       = $this->getCallOptions($args);

        // Get some values
        $type       = $args['type'];
        $cacheKey   = $args['cacheKey'];

        // Check cache
        $cached_result = wp_cache_get( $cacheKey, 'PaginationFu' );
        if(!empty($cached_result)) return $cached_result;

        // check for comments mode and leave if necessary
        if($type == 'comments' && !get_option('page_comments')) return FALSE;

        // Get the page infos
        $pageInfos = $this->getCurrentPageAndTotalPages($args);
        if($pageInfos === FALSE) return FALSE;

        // Extract information
        $page = $pageInfos['page'];
        $pages = $pageInfos['pages'];

        // Next/prev pages
        $previousPage = max(1, $page-1);
        $nextPage = min($page+1, $pages);

        // next and prev text
        $is_reverse = ($type == 'comments') ? $this->options['reverse_comments_list'] : $this->options['reverse_list'];

        if($type == 'comments')
        {
            $prev_text = $is_reverse ? ($this->options['html_comments_older'].$this->options['html_right_icon']) : ($this->options['html_left_icon'].$this->options['html_comments_older']);
            $next_text = $is_reverse ? ($this->options['html_left_icon'].$this->options['html_comments_newer']) : ($this->options['html_comments_newer'].$this->options['html_right_icon']);
        }
        else
        {
            $prev_text = $is_reverse ? ($this->options['html_newer'].$this->options['html_right_icon']) : ($this->options['html_left_icon'].$this->options['html_newer']);
            $next_text = $is_reverse ? ($this->options['html_left_icon'].$this->options['html_older']) : ($this->options['html_older'].$this->options['html_right_icon']);
        }

        // Generate link array
        $items = $this->enumerator->renderItems($page, $pages, $args);

        // Create the list items
        $listItems = array();

        // embed "previous" link
        if($page > 1 || $this->options['always_show_navlinks'])
        {
            $listItems[] = '<li>'.$this->rendererLinks->render($prev_text, $previousPage, "prev newer", $page == 1, $args).'</li>';
        }

        // add page items
        foreach($items as $item)
        {
            $listItems[] = "<li>$item</li>";
        }

        // embed "next" link
        if($page < $pages || $this->options['always_show_navlinks'])
        {
            $listItems[] = '<li>'.$this->rendererLinks->render($next_text, $nextPage, "next older", $page == $pages, $args).'</li>';
        }

        // revert the list if necessary
        if($is_reverse) $listItems = array_reverse($listItems);
        $class    = $this->options['main_class'];
        if($type == 'comments') $class = $this->options['main_comments_class'];
        $content  = str_ireplace('{class}', $class, $this->options['html_main_start']);
        $content .= str_ireplace('{class}', $class, $this->options['html_list_start']);
        $content .= implode('', $listItems);
        $content .= $this->options['html_list_end'];
        $content .= $this->options['html_main_end']."\n";

        // Apply filters and return
        $filtered_content = apply_filters('render_pagination_fu', $content);

        // add to chache
        wp_cache_set( $cacheKey, $filtered_content, 'PaginationFu' );
        return $filtered_content;
    }

    /**
     * Gets the current page and the total page number
     * @return array|bool The page information or FALSE in case of an error
     */
    function getCurrentPageAndTotalPages($args)
    {
        global $wp_query, $wpdb;

        $page = 0;
        $pages = 0;

        if($args['type'] == 'comments')
        {
            $page = get_query_var('cpage');
        	$posts_per_page = get_option('comments_per_page');

            // correct for nested comments
            $result = $wpdb->get_results( $wpdb->prepare( "
                        		SELECT COUNT(*) AS count
                        		FROM $wpdb->comments
                        		WHERE comment_post_ID >= %d
                        			AND (comment_parent > 0
                        				AND comment_approved > 0)" ,
                        		$wp_query->post->ID ));
            $difference = $result[0]->count;
            $pages = intval(ceil(($wp_query->comment_count-$difference) / $posts_per_page));
            $page = max(min($page, $pages), 1);

            // do not render if there is only one page
            if($pages == 1 && !$this->options['always_show_comments_pagination']) return FALSE;
        }
        elseif(is_home() || is_archive())
        {
            // Get the current page
            $page = get_query_var('paged');
            $page = !empty($page) ? max(intval($page), 1) : 1;

            // Get the total number of pages
            $posts_per_page = max(intval(get_query_var('posts_per_page')), 1);
            $pages = max(intval(ceil($wp_query->found_posts / $posts_per_page)), 1);
        }
        elseif(is_single())
        {
            // are we coming from an archive?
            if($this->options['enable_cat_browsing'] && !empty($wp_query->query['category_name']))
            {
                $pages  = PaginationFuRenderer::getPageCountFromCategory();
                $page   = PaginationFuRenderer::getPageIdFromCategory($pages);
            }
            else
            {
                // TODO: Was ist mit passwortgeschützten Seiten? Versteckten Seiten? Unveröffentlichten Seiten?
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

        // return the information
        return array('page' => $page, 'pages' => $pages);
    }

    /**
    * Adds a settings link to the plugin page
    */
    function filterPluginActions($links, $file)
    {
        $settings_link = '<a href="options-general.php?page=' . plugin_basename(__FILE__) . '">' . __('Settings', 'propimgscale') . '</a>';
        array_unshift($links, $settings_link); // before other links
        return $links;
    }

    /**
    * Registers the options page
    */
    function registerOptionsPage()
    {
        if ( function_exists('add_options_page') )
        {
            add_options_page(__('Pagination Fu! settings', 'pagination_fu'), __('Pagination Fu!', 'pagination_fu'), 8, __FILE__, array(&$this, 'renderOptionsPage'));
        }
    }

    /**
    * Renders the options page
    */
    function renderOptionsPage()
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

} // class exists

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