<?php
/*
Plugin Name: WP-Syntax
Plugin URI: http://wordpress.org/extend/plugins/wp-syntax/
Description: Syntax highlighting using <a href="http://qbnz.com/highlighter/">GeSHi</a> supporting a wide range of popular languages.  Wrap code blocks with <code>&lt;pre lang="LANGUAGE" line="1"&gt;</code> and <code>&lt;/pre&gt;</code> where <code>LANGUAGE</code> is a geshi supported language syntax.  The <code>line</code> attribute is optional.
Author: Steven A. Zahm
Version: 0.9.12
Author URI: http://connections-pro.com
*/

#  Original Author: Ryan McGeary

#
#  Copyright (c) 2007-2009 Ryan McGeary 2010 Steven A. Zahm
#
#  This file is part of WP-Syntax.
#
#  WP-Syntax is free software; you can redistribute it and/or modify it under
#  the terms of the GNU General Public License as published by the Free
#  Software Foundation; either version 2 of the License, or (at your option)
#  any later version.
#
#  WP-Syntax is distributed in the hope that it will be useful, but WITHOUT ANY
#  WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
#  FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
#  details.
#
#  You should have received a copy of the GNU General Public License along
#  with WP-Syntax; if not, write to the Free Software Foundation, Inc., 59
#  Temple Place, Suite 330, Boston, MA 02111-1307 USA
#

// Override allowed attributes for pre tags in order to use <pre lang=""> in
// comments. For more info see wp-includes/kses.php
if (!CUSTOM_TAGS) {
    $allowedposttags['pre'] = array(
        'lang' => array(),
        'line' => array(),
        'escaped' => array(),
        'style' => array(),
        'width' => array(),
        'highlight' => array()
    );
    //Allow plugin use in comments
    $allowedtags['pre'] = array(
        'lang' => array(),
        'line' => array(),
        'escaped' => array(),
        'highlight' => array()
    );
}

include_once("geshi/geshi.php");

if (!defined("WP_CONTENT_URL")) define("WP_CONTENT_URL", get_option("siteurl") . "/wp-content");
if (!defined("WP_PLUGIN_URL"))  define("WP_PLUGIN_URL",  WP_CONTENT_URL        . "/plugins");

function wp_syntax_head()
{
  /*$css_url = WP_PLUGIN_URL . "/wp-syntax/wp-syntax.css";
  if (file_exists(TEMPLATEPATH . "/wp-syntax.css"))
  {
    $css_url = get_bloginfo("template_url") . "/wp-syntax.css";
  }
  echo "\n".'<link rel="stylesheet" href="' . $css_url . '" type="text/css" media="screen" />'."\n";*/

  $css_url = WP_PLUGIN_URL . "/wp-syntax/wp-syntax.css";
  if (file_exists(STYLESHEETPATH . "/wp-syntax.css"))
  {
    $css_url = get_bloginfo("stylesheet_directory") . "/wp-syntax.css";
  }
  echo "\n".'<link rel="stylesheet" href="' . $css_url . '" type="text/css" media="screen" />'."\n";
}

function wp_syntax_code_trim($code)
{
    // special ltrim b/c leading whitespace matters on 1st line of content
    $code = preg_replace("/^\s*\n/siU", "", $code);
    $code = rtrim($code);
    return $code;
}

function wp_syntax_substitute(&$match)
{
    global $wp_syntax_token, $wp_syntax_matches;

    $i = count($wp_syntax_matches);
    $wp_syntax_matches[$i] = $match;

    return "\n\n<p>" . $wp_syntax_token . sprintf("%03d", $i) . "</p>\n\n";
}

function wp_syntax_line_numbers($code, $start)
{
    $line_count = count(explode("\n", $code));
    $output = "<pre>";
    for ($i = 0; $i < $line_count; $i++)
    {
        $output .= ($start + $i) . "\n";
    }
    $output .= "</pre>";
    return $output;
}

function wp_syntax_highlight($match)
{
    global $wp_syntax_matches;
 
    $i = intval($match[1]);
    $match = $wp_syntax_matches[$i];
 
    $language = strtolower(trim($match[1]));
    $line = trim($match[2]);
    $escaped = trim($match[3]);
 
    $code = wp_syntax_code_trim($match[5]);
    if ($escaped == "true") $code = htmlspecialchars_decode($code);
 
    $geshi = new GeSHi($code, $language);
    $geshi->enable_keyword_links(false);
    do_action_ref_array('wp_syntax_init_geshi', array(&$geshi));
 
    //START LINE HIGHLIGHT SUPPORT
    $highlight = array();
    if ( !empty($match[4]) )
    {
        $highlight = strpos($match[4],',') == false ? array($match[4]) : explode(',', $match[4]);
 
	$h_lines = array();
	for( $i=0; $i<sizeof($highlight); $i++ )
	{
		$h_range = explode('-', $highlight[$i]);
 
		if( sizeof($h_range) == 2 )
			$h_lines = array_merge( $h_lines, range($h_range[0], $h_range[1]) );
		else
			array_push($h_lines, $highlight[$i]);
	}

        $geshi->highlight_lines_extra( $h_lines );
    }
    //END LINE HIGHLIGHT SUPPORT
 
    $output = "\n<div class=\"wp_syntax\">";
 
    if ($line)
    {
        $output .= "<table><tr><td class=\"line_numbers\">";
        $output .= wp_syntax_line_numbers($code, $line);
        $output .= "</td><td class=\"code\">";
        $output .= $geshi->parse_code();
        $output .= "</td></tr></table>";
    }
    else
    {
        $output .= "<div class=\"code\">";
        $output .= $geshi->parse_code();
        $output .= "</div>";
    }
    return
 
    $output .= "</div>\n";
 
    return $output;
}

function wp_syntax_before_filter($content)
{
    return preg_replace_callback(
        "/\s*<pre(?:lang=[\"']([\w-]+)[\"']|line=[\"'](\d*)[\"']|escaped=[\"'](true|false)?[\"']|highlight=[\"']((?:\d+[,-])*\d+)[\"']|\s)+>(.*)<\/pre>\s*/siU",
        "wp_syntax_substitute",
        $content
    );
}

function wp_syntax_after_filter($content)
{
    global $wp_syntax_token;

     $content = preg_replace_callback(
         "/<p>\s*".$wp_syntax_token."(\d{3})\s*<\/p>/si",
         "wp_syntax_highlight",
         $content
     );

    return $content;
}

$wp_syntax_token = md5(uniqid(rand()));
$wp_syntax_matches = array();

// Add styling
add_action('wp_head', 'wp_syntax_head');

// We want to run before other filters; hence, a priority of 0 was chosen.
// The lower the number, the higher the priority.  10 is the default and
// several formatting filters run at or around 6.
add_filter('the_content', 'wp_syntax_before_filter', 0);
add_filter('the_excerpt', 'wp_syntax_before_filter', 0);
add_filter('comment_text', 'wp_syntax_before_filter', 0);

// We want to run after other filters; hence, a priority of 99.
add_filter('the_content', 'wp_syntax_after_filter', 99);
add_filter('the_excerpt', 'wp_syntax_after_filter', 99);
add_filter('comment_text', 'wp_syntax_after_filter', 99);

?>
