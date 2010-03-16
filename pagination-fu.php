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
    function getUrl($page = 1)
    {
        return get_pagenum_link(intval($page));
    }

    /**
     * Gets the link title from the specified page number.
     * @param page int The page number.
     * @return The page title or the page number, if no title could be generated.
     */
    function getTitleFromPage($page, $default = FALSE)
    {
        global $PaginationFu;
        $defaultReturnValue = empty($default) ? $page : $default;
        $defaultReturnValue = str_ireplace('{page}', $defaultReturnValue, $PaginationFu->options['alternative_title']);
        if(!$PaginationFu->options['do_title_lookup']) return $defaultReturnValue;

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
}

/**
 * A renderer for the page items.
 */
class PaginationFuPageRenderer extends PaginationFuRenderer
{
    /**
     * @var The opening tag for an active page link
     */
    var $openTagActive      = '<a class="page page-{page}" href="{url}" title="{title}">';

    /**
     * @var The closing tag for an active page link
     */
    var $closeTagActive     = '</a>';

    /**
     * @var The opening tag for the current page
     */
    var $openTagCurrent      = '<span class="page page-{page} current" title="{title}">';

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
    function render($value, $page, $is_current = FALSE)
    {
        $url          = $this->getUrl($page);

        $openTag      = !$is_current ? $this->openTagActive : $this->openTagCurrent;
        $closeTag     = !$is_current ? $this->closeTagActive : $this->closeTagCurrent;

        $title        = $this->getTitleFromPage($page, $value);
        $searchArray  = array('{url}', '{title}', '{page}');
        $replacements = array( $url,    $title,    $page);

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
    function render($value, $page, $class = 'next', $is_current = FALSE)
    {
        $url          = $this->getUrl($page);

        $openTag      = !$is_current ? $this->openTagActive : $this->openTagCurrent;
        $closeTag     = !$is_current ? $this->closeTagActive : $this->closeTagCurrent;

        $searchArray  = array('{url}', '{title}', '{page}', '{class}');
        $replacements = array( $url,    $value,    $page,    $class);

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
    function renderItems($page, $pages)
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
        $this->renderRange($items, $leftBlock['start'], $leftBlock['end'], $page);
        if($centerBlockNeeded)
        {
            $this->renderEllipsis($items);
            $this->renderRange($items, $centerBlock['start'], $centerBlock['end'], $page);
        }
        if($rightBlockNeeded)
        {
            $this->renderEllipsis($items);
            $this->renderRange($items, $rightBlock['start'], $rightBlock['end'], $page);
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
    function renderRange(&$items, $start, $end, $current)
    {
        global $PaginationFu;
        if($start < 1 || $end < 1 || $start > $end || $end < $start) return;
        for($i=$start; $i<=$end; ++$i)
        {
            $is_current = ($i == $current);
            $items[] = $PaginationFu->rendererPage->render($i, $i, $is_current);
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
        'html_main_start'           => '<div class="{class}" role="navigation">',
        'html_main_end'             => '</div>',
        'html_list_start'           => '<ol class="{class}">',
        'html_list_end'             => '</ol>',
        'reverse_list'              => FALSE,
        'html_right_icon'           => '&#160;&#187;',
        'html_left_icon'            => '&#171;&#160;',
        'html_older'                => 'older',
        'html_newer'                => 'newer',
        'always_show_navlinks'      => FALSE,
        'do_title_lookup'           => TRUE,
        'alternative_title'         => 'Page {page}',

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
        $this->defaultOptions['alternative_title']  = __('Page {page}', 'pagination_fu');

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
    function render()
    {
        echo $this->getRendered();
    }

    /**
     * Renders the pagination
     */
    function getRendered()
    {
        // Unterscheidung zwischen:
        //  Index
        //  Single
        //  Archiv
        //  Kommentar

        // Title provider für Seitentitel, wenn pro Seite nur ein Artikel angezeigt wird!

        global $wp_query;

        // Get the current page
        $page = get_query_var('paged');
        $page = !empty($page) ? max(intval($page), 1) : 1;

        // Get the total number of pages
        $posts_per_page = max(intval(get_query_var('posts_per_page')), 1);
        $pages = max(intval(ceil($wp_query->found_posts / $posts_per_page)), 1);

        // Next/prev pages
        $previousPage = max(1, $page-1);
        $nextPage = min($page+1, $pages);

        // next and prev text
        $is_reverse = $this->options['reverse_list'];
        $prev_text = $is_reverse ? ($this->options['html_newer'].$this->options['html_right_icon']) : ($this->options['html_left_icon'].$this->options['html_newer']);
        $next_text = $is_reverse ? ($this->options['html_left_icon'].$this->options['html_older']) : ($this->options['html_older'].$this->options['html_right_icon']);

        // Generate link array
        $items = $this->enumerator->renderItems($page, $pages);

        // Create the list items
        $listItems = array();

        // embed "previous" link
        if($page > 1 || $this->options['always_show_navlinks'])
        {
            $listItems[] = '<li>'.$this->rendererLinks->render($prev_text, $previousPage, "prev newer", $page == 1).'</li>';
        }

        // add page items
        foreach($items as $item)
        {
            $listItems[] = "<li>$item</li>";
        }

        // embed "next" link
        if($page < $pages || $this->options['always_show_navlinks'])
        {
            $listItems[] = '<li>'.$this->rendererLinks->render($next_text, $nextPage, "next older", $page == $pages).'</li>';
        }

        // revert the list if necessary
        if($is_reverse) $listItems = array_reverse($listItems);
        $content  = str_ireplace('{class}', $this->options['main_class'], $this->options['html_main_start']);
        $content .= str_ireplace('{class}', $this->options['main_class'], $this->options['html_list_start']);
        $content .= implode('', $listItems);
        $content .= $this->options['html_list_end'];
        $content .= $this->options['html_main_end']."\n";

        // Apply filters and return
        return apply_filters('render_pagination_fu', $content);
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
    function get_PaginationFu()
    {
        global $PaginationFu;
        return $PaginationFu->getRendered();
    }
}

if(!function_exists('PaginationFu')) {
    /**
     * Renders the pagination
     */
    function PaginationFu()
    {
        global $PaginationFu;
        return $PaginationFu->render();
    }
}