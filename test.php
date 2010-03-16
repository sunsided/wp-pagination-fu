<?php

    $pages = 100;
    $defaultPages = $pages;
    $page = 50;

    if(!empty($_REQUEST['pages'])) $pages = max(intval($_REQUEST['pages']), 1);
    if(!empty($_REQUEST['page'])) $page = min(max(intval($_REQUEST['page']), 1), $pages);

    $pagesAroundCurrent = 3;
    $pagesAtEdges = 1;

    function renderEllipsis()
    {
        $ellipsisTag = '&#133;';
        echo "[$ellipsisTag] ";
    }

    function renderRange($start, $end, $current, $anchor, $ellipsis = 0)
    {
        if($start < 1 || $end < 1 || $start > $end || $end < $start) return;
        global $pages, $defaultPages;

        if($ellipsis < 0) renderEllipsis();
        for($i=$start; $i<=$end; ++$i)
        {
            if($ellipsis == 0) echo '<span style="color: blue">';
            if($i == $current) echo '<strong style="color: red">';

            $url = $_SERVER['PHP_SELF'] . '?page=%d';
            if($pages != $defaultPages) $url .= "&pages=%d";
            $url = sprintf($url, $i, $pages, "behavior1");
            $url .= "#".$anchor;

            $text = $i > 9 ? "[$i]" : "[0$i]";
            if($i != $current) $text = "<a href=\"$url\" title=\"$i\">$text</a>";

            echo $text;

            if($i == $current) echo '</strong>';
            if($ellipsis == 0) echo '</span>';
            echo ' ';
        }
        if($ellipsis > 0) renderEllipsis();
    }

    // Behavior 1  -------------------------------------------------------------
    echo "<p><a id=\"behavior1\" name=\"behavior1\" />Behavior 1:<br />";
    echo "<pre>";
    renderRange(1, $pages, $page, "behavior1");
    echo "</pre></p>";

    // Behavior 2 --------------------------------------------------------------
    $rangeWidth = 2*$pagesAroundCurrent + 1;
    $minEllipsisWidth = 2;
    $comparisonWidth = $rangeWidth + 2*$pagesAtEdges + 2*$minEllipsisWidth;
    $blockWidth = $comparisonWidth - 2;

    echo "<p><a id=\"behavior2\" name=\"behavior2\" />Behavior 2:<br />";
    echo "<pre>";
    echo "* Pages at edges:        ". $pagesAtEdges ."<br/>";
    echo "* Pages around current:  ". $pagesAroundCurrent ."<br/>";
    echo "* Min ellipsis width:    ". $minEllipsisWidth ."<br/>";
    echo "* Comparison width:      ". $comparisonWidth ."<br/>";
    echo "* Block width:           ". $blockWidth ."<br/>";
    if($pages > $comparisonWidth)
    {
        // ellipsis
        $currentLeftEdge = $page - $pagesAroundCurrent;
        $currentRightEdge = $page + $pagesAroundCurrent;

        // Ermitteln, ob eine Ellipse auf der linken Seite benötigt wird
        $leftEllipsisRequired = ($currentLeftEdge - $minEllipsisWidth) > $minEllipsisWidth;

        // Ermitteln, ob eine Ellipse auf der rechen Seite benötigt wird
        $rightEllipsisRequired = ($currentRightEdge + $minEllipsisWidth) < $pages;

        echo "* Left ellipsis needed:  ". ($leftEllipsisRequired?"yes":"no") ."<br/>";
        echo "* Right ellipsis needed: ". ($rightEllipsisRequired?"yes":"no") ."<br/>";

        // define default regions
        $fragmentLeftStart = 1;
        $fragmentLeftEnd = $pagesAtEdges;
        $fragmentCenterStart = $currentLeftEdge;
        $fragmentCenterEnd = $currentRightEdge;
        $fragmentRightStart = $pages - $pagesAtEdges + 1;
        $fragmentRightEnd = $pages;

        // edge case: left ellipsis not required
        if(!$leftEllipsisRequired)
        {
            $fragmentCenterStart = 1;
            $fragmentLeftStart = 0;
        }

        // edge case: right ellipsis not required
        if(!$rightEllipsisRequired)
        {
            $fragmentCenterEnd = $pages;
            $fragmentRightStart = 0;
        }

        // correction for current pages near the edges
        $width = ($fragmentCenterEnd+1-$fragmentCenterStart) + 1 + 2*$pagesAtEdges;
        $difference = $blockWidth - $width + $minEllipsisWidth;
        echo "* Difference:            ". $difference ."<br/>";

        if(!$leftEllipsisRequired && $rightEllipsisRequired)
        {
            $fragmentCenterEnd += $difference;
        }
        else if($leftEllipsisRequired && !$rightEllipsisRequired)
        {
            $fragmentCenterStart -= $difference;
        }

        // rendering of the pages
        renderRange($fragmentLeftStart, $fragmentLeftEnd, $page, "behavior2", 1);
        renderRange($fragmentCenterStart, $fragmentCenterEnd, $page, "behavior2", 0);
        renderRange($fragmentRightStart, $fragmentRightEnd, $page, "behavior2", -1);
    }
    else
    {
        renderRange(1, $pages, $page, "behavior2");
    }
    echo "</pre></p>";


    // Behavior 3 --------------------------------------------------------------
    echo "<p><a id=\"behavior3\" name=\"behavior3\" />Behavior 3:<br />";
    echo "<pre>";
    $minPagesAtStart   = $pagesAtEdges + 2;
    $minPagesAtEnd     = $pagesAtEdges;

    echo "* Pages at left:         ". $minPagesAtStart ."<br/>";
    echo "* Pages at end:          ". $minPagesAtEnd ."<br/>";
    echo "* Pages around current:  ". $pagesAroundCurrent ." (2x --&gt; ".(2*$pagesAroundCurrent).")<br/>";
    echo "<strong>* Total items:           ". ((2*$pagesAroundCurrent) + $minPagesAtStart + $minPagesAtEnd + 1 + 2)."</strong><br />";

    // (01) [02] [03] [04] [05] [06] [07] [08] [09] [10] [11] [12] [13] [14] [15]
    //
    // (01) [02] [03] [04] [05] [06] [07] [08] [09] [10] [..] [14] [15]
    // [01] [02] [03] (04) [05] [06] [07] [08] [09] [10] [..] [14] [15]
    // [01] [02] [03] [04] [05] (06) [07] [08] [09] [10] [..] [14] [15]
    // [01] [02] [03] [04] [05] [06] (07) [08] [09] [10] [..] [14] [15]  <-- 15/2 = 7 (r1), der 7. Punkt (Index 6) ist der Pivotpunkt
    // [01] [02] [..] [05] [06] [07] (08) [09] [10] [11] [..] [14] [15]
    // [01] [02] [..] [06] [07] [08] (09) [10] [11] [12] [..] [14] [15]
    // [01] [02] [..] [06] [07] [08] [09] (10) [11] [12] [13] [14] [15]
    // [01] [02] [..] [06] [07] [08] [09] [10] (11) [12] [13] [14] [15]
    // [01] [02] [..] [06] [07] [08] [09] [10] [11] [12] (13) [14] [15]
    // [01] [02] [..] [06] [07] [08] [09] [10] [11] [12] [13] [14] (15)

    $pivot = intval($pages / 2);
    echo "* Pivot element:         ". $pivot ."<br/>";

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

    $diffLeftCenter     = $centerBlock['start'] - $leftBlock['end'] - 1;
    echo "* Diff left->center:     ". $diffLeftCenter ."<br/>";

    // Merge center block with left block
    if($diffLeftCenter <= 1)
    {
        $overlap = ($leftBlock['end']+1) - $centerBlock['start'] + 1;

        echo "* Left merged w/ center. Overlap: ".$overlap."<br/>";
        if($centerBlock['end'] > $leftBlock['end']) $leftBlock['end'] = $centerBlock['end'];
        $leftBlock['end'] += $overlap;
        $leftBlock['end'] = min($pages, $leftBlock['end']);

        $centerBlock['start'] = $leftBlock['start'];
        $centerBlock['end'] = $leftBlock['end'];

        $centerBlockNeeded = FALSE;
    }

    $diffRightCenter    = $rightBlock['start'] - $centerBlock['end'] - 1;
    echo "* Diff center->right:    ". $diffRightCenter ."<br/>";

    // Merge center block with right block
    if($diffRightCenter <= 1)
    {
        $overlap = $centerBlock['end'] - ($rightBlock['start']-1) + 1;
        echo "* Center merged w/ right. Overlap: ".$overlap."<br/>";

        if($centerBlock['start'] < $rightBlock['start']) $rightBlock['start'] = $centerBlock['start'];
        $rightBlock['start'] -= $overlap;
        $rightBlock['start'] = max(1, $rightBlock['start']);

        $centerBlockNeeded = FALSE;
    }

    // Merge right block with left block
    if( $rightBlock['start'] <= $leftBlock['end'] + 1 ||
        $leftBlock['end'] >= $rightBlock['start'] - 1 ||
        ($leftBlock['start'] == $rightBlock['start'] - 1 && $leftBlock['end'] == 0)
        )
    {
        echo "* Left merged w/ right.<br/>";
        if($rightBlock['end'] > $leftBlock['end']) $leftBlock['end'] = $rightBlock['end'];

        $rightBlockNeeded = FALSE;
    }

    print_r($leftBlock);
    print_r($centerBlock);
    print_r($rightBlock);

    // Is a right ellipsis required?
    $needRightEllipsis = $rightBlock['start'] > $pages;

    // Render blocks
    renderRange($leftBlock['start'], $leftBlock['end'], $page, "behavior3");
    if($centerBlockNeeded)  renderRange($centerBlock['start'], $centerBlock['end'], $page, "behavior3", -1);
    if($rightBlockNeeded)   renderRange($rightBlock['start'], $rightBlock['end'], $page, "behavior3", -1);
    if($needRightEllipsis) renderEllipsis();

    echo "</pre></p>";