<?php
// - Extension: Slideshow
// - Version: 0.7
// - Author: PivotX Team
// - Email: admin@pivotx.net
// - Site: http://www.pivotx.net
// - Description: A snippet and widget to add a slideshow to your site or entries/pages/templates.
// - Date: 2010-09-27
// - Identifier: slideshow
// - Required PivotX version: 2.0.2


global $slideshow_config;

$slideshow_config = array(
    'slideshow_width' => "250",
    'slideshow_height' => "180",
    'slideshow_folder' => "slideshow",
    'slideshow_timeout' => "4000",
    'slideshow_limit' => "15",
    'slideshow_orderby' => "date_desc",
    'slideshow_popup' => 'no',
    'slideshow_only_snippet' => 0,
    'slideshow_recursion' => 'no',
    'slideshow_nicenamewithdirs' => 0,
    'slideshow_iptcindex' => "",
    'slideshow_iptcencoding' => "",
);


/**
 * Adds the hook for slideshowAdmin()
 *
 * @see slideshowAdmin()
 */
$this->addHook(
    'configuration_add',
    'slideshow',
    array("slideshowAdmin", "Slideshow")
);


/**
 * Adds the hook for the actual widget. We just use the same
 * as the snippet, in this case.
 *
 * @see smarty_slideshow()
 */
$this->addHook(
    'widget',
    'slideshow',
    "widget_slideshow"
);


/**
 * Add some javascript to the header..
 */
$this->addHook(
    'after_parse',
    'insert_before_close_head',
    "
    <!-- Includes for slideshow extension -->
    <script type='text/javascript' src='[[pivotx_dir]]extensions/slideshow/jquery.slideviewer.1.1.js'></script>
    <script type='text/javascript' src='[[pivotx_dir]]extensions/slideshow/jquery.easing.1.2.js'></script>
    <script type='text/javascript'>
        var slideshow_pathToImage = '[[pivotx_dir]]extensions/slideshow/spinner.gif';
    </script>
    <link href='[[pivotx_dir]]extensions/slideshow/slideshow.css' rel='stylesheet' type='text/css' />\n"
);

// If the hook for the jQuery include in the header was not yet installed, do so now..
$this->addHook('after_parse', 'callback', 'jqueryIncludeCallback');


// Register 'slideshow' as a smarty tag.
$PIVOTX['template']->register_function('slideshow', 'smarty_slideshow');

/**
 * Output a slideshow feed as a widget
 *
 * @return string
 */
function widget_slideshow() {
    global $PIVOTX, $slideshow_config;

    $key = 'slideshow_only_snippet';
    $enabled = getDefault($PIVOTX['config']->get($key), $slideshow_config[$key]);
    if ($enabled) {
        return;
    } else {
        $output = smarty_slideshow(array());
        $output = "\n<div class='widget-lg'>$output</div>\n"; 
        return $output;
    }

}

/**
 * Returns a list of sub directories with absolute paths
 */
function slideshowGetDirs($dir, $recursion='all') {
    $array = array();
    $d = dir($dir);
    while (false !== ($entry = $d->read())) {
        if ($entry!='.' && $entry!='..' && $entry!='.svn') {
            $entry = $entry.DIRECTORY_SEPARATOR;
            if (is_dir($dir.$entry)) {
                $subdirs = slideshowGetDirs($dir.$entry);
                if ($recursion=='all' || count($subdirs) == 0) {
                    $array[] = $dir.$entry;
                }
                if (count($subdirs) > 0) {
                    $array = array_merge($array, $subdirs);
                }
            }
        }
    }
    $d->close();
    return $array;
}

/**
 * Output a slideshow feed as a template
 *
 * @param array $params
 * @return string
 */
function smarty_slideshow($params) {
    global $PIVOTX, $slideshow_config;
    
    static $slideshowcount = 0;

    $js_insert = <<<EOF
<script type="text/javascript">
jQuery(function(){
    jQuery("div#pivotx-slideshow-%count%").slideView();
    setTimeout('slideNext_%count%()', %timeout%);
});

function slideNext_%count%() {

    if( typeof slideNext_%count%.currentslide == 'undefined' ) {
        slideNext_%count%.currentslide = 0;
    }

    var slidewidth = jQuery("div#pivotx-slideshow-%count%").find("li").find("img").width();
    var amountofslides = %amount% - 1; 

    if (amountofslides > slideNext_%count%.currentslide) {
        slideNext_%count%.currentslide++;
    } else {
        slideNext_%count%.currentslide = 0;
    }

    var xpos = -slidewidth * slideNext_%count%.currentslide;
    jQuery("div#pivotx-slideshow-%count%").find("ul").animate({ left: xpos}, 1200, "easeInOutExpo");    

    setTimeout('slideNext_%count%()', %timeout%);

}
</script>
EOF;

    $params = clean_params($params);
    foreach(array('timeout','folder','width','height','limit','orderby','popup','recursion','nicenamewithdirs','iptcindex','iptcencoding') as $key) {
        if (isset($params[$key])) {
            $$key = $params[$key];
        } else {
            $$key = getDefault($PIVOTX['config']->get('slideshow_'.$key), $slideshow_config['slideshow_'.$key]);
        }
    }

    $imagefolder = addTrailingSlash($PIVOTX['paths']['upload_base_path'].$folder);
    $ok_extensions = explode(",", "jpg,jpeg,png,gif");

    if (!file_exists($imagefolder) || !is_dir($imagefolder)) {
        debug("Image folder $imagefolder does not exist.");
        echo("Image folder $imagefolder does not exist.");
        return "";
    } else if (!is_readable($imagefolder)) {
        debug("Image folder $imagefolder is not readable.");
        echo("Image folder $imagefolder is not readable.");
        return "";
    }

    $images = array();

    $key = "";

    if ($recursion == 'no') {
        $dirs = array($imagefolder);
    } else {
        $dirs = slideshowGetDirs($imagefolder, $recursion);
        if ($recursion == 'all') {
            array_unshift($dirs, $imagefolder);
        }
    }
    foreach($dirs as $folder) {
        $dir = dir($folder);
        while (false !== ($entry = $dir->read())) {
            if ( in_array(strtolower(getExtension($entry)), $ok_extensions) ) {
                if (strpos($entry, ".thumb.")>0) {
                    continue;
                }
                $entry = $folder.$entry;
                if ($orderby=='date_asc' || $orderby=='date_desc') {
                    $key = filemtime($entry).rand(10000,99999);
                    $images[$key] = $entry;
                } else {
                    $images[] = $entry;
                }
            }
        }
        $dir->close();
    }

    if ($orderby=='date_asc') {
        ksort($images);
    } else if ($orderby=='date_desc') {
        ksort($images);
        $images = array_reverse($images);
    } else if ($orderby=='alphabet') {
        natcasesort($images);
    } else {
        shuffle($images);
    }

    // Cut it to the desired length..
    $images = array_slice($images, 0, $limit);

    $js_insert = str_replace('%timeout%', $timeout, $js_insert);
    $js_insert = str_replace('%count%', $slideshowcount, $js_insert);
    $js_insert = str_replace('%amount%', count($images), $js_insert);
    $PIVOTX['extensions']->addHook('after_parse', 'insert_before_close_head', $js_insert);

    // If a specific popup type is selected execute the callback.
    if ($popup != 'no') {
        $callback = $popup."IncludeCallback";
        if (function_exists($callback)) {
            $PIVOTX['extensions']->addHook('after_parse', 'callback', $callback);
        } else {
            debug("There is no function '$callback' - the popups won't work.");
        }
    }

    $output = "\n<div id=\"pivotx-slideshow-$slideshowcount\" class=\"svw\">\n<ul>\n";

    foreach ($images as $image) {
        $file = $image;
        $image = str_replace($PIVOTX['paths']['upload_base_path'], '', $image);
        $image = str_replace(DIRECTORY_SEPARATOR, '/', $image);
        $nicefilename = formatFilename($image, $nicenamewithdirs);

        $title = false;
        if ($iptcindex) {
            getimagesize($file, $iptc);
            if (is_array($iptc) && $iptc['APP13']) {
                $iptc = iptcparse($iptc['APP13']);
                $title = $iptc[$iptcindex][0];
                if ($iptcencoding) {
                    $title = iconv($iptcencoding, 'UTF-8', $title);
                }
                $title = cleanAttributes($title);
            }
        }
        if (!$title) {
            $title = $nicefilename;
        }
 
        $line = "<li>\n";
        if ($popup != 'no') {
            $line .= sprintf("<a href=\"%s%s\" class=\"$popup\" rel=\"slideshow\" title=\"%s\">\n",
                $PIVOTX['paths']['upload_base_url'], $image, $title);
        }
        $line .= sprintf("<img src=\"%sincludes/timthumb.php?src=%s&amp;w=%s&amp;h=%s&amp;zc=1\" " .
                "alt=\"%s\" width=\"%s\" height=\"%s\" />\n",
            $PIVOTX['paths']['pivotx_url'], rawurlencode($image), $width, $height, $title, $width, $height);
        if ($popup != 'no') {
            $line .= "</a>";
        }
        $line .= "</li>\n";
        $output .= $line;

    }

    $output .= "</ul>\n</div>\n";
    
    $slideshowcount++;

    return $output;


}



/**
 * The configuration screen for slideshow
 *
 * @param unknown_type $form_html
 */
function slideshowAdmin(&$form_html) {
    global $PIVOTX, $slideshow_config;

    $form = $PIVOTX['extensions']->getAdminForm('slideshow');

    $form->add( array(
        'type' => 'text',
        'name' => 'slideshow_folder',
        'label' => __('Default folder name'),
        'value' => '',
        'error' => __('That\'s not a proper folder name!'),
        'text' => __("The name of the folder in where the images are that Slideshow should use. This should be a folder inside your <tt>images</tt> folder. So if you input <tt>slideshow</tt>, the slideshow will look in the <tt>/images/slideshow/</tt> folder. Don't start or finish with a slash."),
        'size' => 32,
        'isrequired' => 1,
        'validation' => 'string|minlen=1|maxlen=32'
    ));

    $form->add( array(
        'type' => 'text',
        'name' => 'slideshow_timeout',
        'label' => __('Default timeout'),
        'value' => '',
        'error' => __('Error!'),
        'text' => __('The time (in milliseconds) between each image in the slideshow.'),
        'size' => 6,
        'isrequired' => 1,
        'validation' => 'integer|min=1|max=10000'
    ));

    $form->add( array(
        'type' => 'text',
        'name' => 'slideshow_width',
        'label' => __('Default width'),
        'value' => '',
        'error' => __('Error!'),
        'text' => "",
        'size' => 6,
        'isrequired' => 1,
        'validation' => 'integer|min=1|max=500'
    ));

    $form->add( array(
        'type' => 'text',
        'name' => 'slideshow_height',
        'label' => __('Default height'),
        'value' => '',
        'error' => __('Error!'),
        'text' => __("The width and height of the thumbnails in the widget. The borders are added to this, so the total dimensions of the widget will be 6 pixels wider and taller."),
        'size' => 6,
        'isrequired' => 1,
        'validation' => 'string|min=1|max=500'
    ));

    $form->add( array(
        'type' => 'text',
        'name' => 'slideshow_limit',
        'label' => __('Limit'),
        'value' => '',
        'error' => __('Error!'),
        'text' => __("This limits the number of items that are shown. If you set it too high, it will take longer to load your site."),
        'size' => 6,
        'isrequired' => 1,
        'validation' => 'string|min=1|max=500'
    ));

    $form->add( array(
        'type' => 'select',
        'name' => 'slideshow_orderby',
        'label' => __('Order by'),
        'value' => '',
        'firstoption' => __('Select'),
        'options' => array(
            'date_asc' => __("Date ascending"),
            'date_desc' => __("Date descending"),
            'alphabet' => __("Alphabet"),
            'random' => __("Random")
        ),
        'isrequired' => 1,
        'validation' => 'any',
        'text' => __("Select the order in which the images are shown.")
    ));

    $form->add( array(
        'type' => 'select',
        'name' => 'slideshow_popup',
        'label' => __("Use popup"),
        'options' => array(
            'no' => __("No"),
            'thickbox' => __("Thickbox"),
            'fancybox' => __("Fancybox"),
        ),
        'isrequired' => 1,
        'validation' => 'any',
        'text' => __("Select which popup type images are displayed in when clicked.")
    ));

    $form->add( array(
        'type' => 'select',
        'name' => 'slideshow_recursion',
        'label' => __('Use recursion'),
        'options' => array(
            'no' => __("No"),
            'leaf' => __("Leaf"),
            'all' => __("All"),
        ),
        'isrequired' => 1,
        'validation' => 'any',
        'text' => sprintf('<p>%s</p>', 
            __("If recursion is enabled images from either all subdirectories or just the leaf subdirectories will be included in the slide show."))
    ));

    $form->add( array(
        'type' => 'checkbox',
        'name' => 'slideshow_only_snippet',
        'label' => __("Use only as snippet"),
        'text' => sprintf(__("Yes, I don't want %s to appear among the widgets."), "Slideshow")
    ));

    $form->add( array(
        'type' => 'checkbox',
        'name' => 'slideshow_nicenamewithdirs',
        'label' => __("Use directories in title"),
        'text' => __("Yes, include directory names to the automatically generated image titles.")
    ));

    $form->add( array(
        'type' => 'custom',
        'text' => sprintf("<tr><td colspan='2'><h3>%s</h3> <em>(%s)</em></td></tr>",
            __('Advanced Configuration'),
            __('Warning! These features are experimental, so use them with caution!') )
    ));

    $form->add( array(
        'type' => 'text',
        'name' => 'slideshow_iptcindex',
        'label' => __('IPTC Index'),
        'value' => '',
        'text' => __("Index of image title in IPTC table. (Picasa coments use '2#120'). Leave blank to generate a nicename."),
        'size' => 32,
    ));

    $form->add( array(
        'type' => 'text',
        'name' => 'slideshow_iptcencoding',
        'label' => __('IPTC Encoding'),
        'value' => '',
        'text' => __("Encoding of image IPTC texts. (Use the iconv encoding names.) Leave blank for no decoding."),
        'size' => 32,
    ));


    /**
     * Add the form to our (referenced) $form_html. Make sure you use the same key
     * as the first parameter to $PIVOTX['extensions']->getAdminForm
     */
    $form_html['slideshow'] = $PIVOTX['extensions']->getAdminFormHtml($form, $slideshow_config);



}


?>
