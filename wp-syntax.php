<?php
/*
Plugin Name: WP-Syntax
Plugin URI: http://wordpress.org/extend/plugins/wp-syntax/
Description: Syntax highlighting using <a href="http://qbnz.com/highlighter/">GeSHi</a> supporting a wide range of popular languages.  Wrap code blocks with <code>&lt;pre lang="LANGUAGE" line="1"&gt;</code> and <code>&lt;/pre&gt;</code> where <code>LANGUAGE</code> is a geshi supported language syntax.  The <code>line</code> attribute is optional.
Author: Steven A. Zahm
Version: 0.9.13
Author URI: http://connections-pro.com
*/

#  Original Author: Ryan McGeary
#  Author: Steven A. Zahm
#  Author: Steffen Vogel <info@steffenvogel.de>

#
#  Copyright (c) 2007-2013 Ryan McGeary; Steven A. Zahm
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
/*if (!CUSTOM_TAGS) {
  $allowedposttags['pre'] = array(
    'lang' => array(),
    'line' => array(),
    'escaped' => array(),
    'style' => array(),
    'width' => array(),
	'highlight' => array(),
    'src' => array()
  );
  //Allow plugin use in comments
  $allowedtags['pre'] = array(
    'lang' => array(),
    'line' => array(),
    'escaped' => array(),
	'highlight' => array()
  );
}*/

include_once("geshi/geshi.php");

if (!defined("WP_CONTENT_URL")) define("WP_CONTENT_URL", get_option("siteurl") . "/wp-content");
if (!defined("WP_PLUGIN_URL"))  define("WP_PLUGIN_URL",  WP_CONTENT_URL        . "/plugins");

function wp_syntax_change_mce_options($init) {
    $ext = 'pre[id|name|class|style|lang|line|escaped|hightlight|src]';
    
    if ( isset($init["extended_valid_elements"]) )
	{
        $init["extended_valid_elements"] .= "," . $ext;
    } else {
        $init["extended_valid_elements"] = $ext;
    }

    return $init;
}

function wp_syntax_style()
{
	$url = WP_PLUGIN_URL . "/wp-syntax/wp-syntax.css";
	
	if ( file_exists(STYLESHEETPATH . "/wp-syntax.css") )
	{
		$url = get_bloginfo("stylesheet_directory") . "/wp-syntax.css";
	}
	
	wp_enqueue_style( 'wp-syntax-css', $url );
}

function wp_syntax_scripts()
{
	$url = WP_PLUGIN_URL . "/wp-syntax/wp-syntax.js";
	
	wp_enqueue_script( 'wp-syntax-js', $url );
}

// special ltrim b/c leading whitespace matters on 1st line of content
function wp_syntax_code_trim($code)
{
    $code = preg_replace("/^\s*\n/siU", "", $code);
    $code = rtrim($code);
    return $code;
}

function wp_syntax_substitute(&$match)
{
    global $wp_syntax_token, $wp_syntax_matches;

    $i = count($wp_syntax_matches);
    $wp_syntax_matches[$i] = $match;

    return "\n\n<p>" . $wp_syntax_token . sprintf('%03d', $i) . "</p>\n\n";
}

function wp_syntax_line_numbers($code, $start)
{
    $line_count = count(explode("\n", $code));
    $output = '<pre>';
    for ($i = 0; $i < $line_count; $i++)
    {
        $output .= ($start + $i) . "\n";
    }
    $output .= '</pre>';
    return $output;
}

function wp_syntax_caption($url) {
    $parsed = parse_url($url);
    $path = pathinfo($parsed['path']);
    $caption = '';

    if ( ! isset($path['filename']) )
	{
		return;
    }

    if ( isset($parsed['scheme']) )
	{
        $caption .= '<a href="' . $url . '">';
    }

    if ( isset( $parsed["host"] ) && $parsed["host"] == 'github.com' )
	{
        $caption .= substr($parsed['path'], strpos($parsed['path'], '/', 1)); /* strip github.com username */
    } else {
        $caption .= $parsed['path'];
    }

    /* $caption . $path["filename"];
    if (isset($path["extension"])) {
        $caption .= "." . $path["extension"];
    }*/

    if ( isset($parsed['scheme']) )
	{
        $caption .= '</a>';
    }

    return $caption;
}

function wp_syntax_highlight($match)
{
    global $wp_syntax_matches;
 
    $i = intval($match[1]);
    $match = $wp_syntax_matches[$i];
 
    $language = strtolower(trim($match[1]));
    $line = trim($match[2]);
    $escaped = trim($match[3]); 
    $caption = wp_syntax_caption($match[5]);
    $code = wp_syntax_code_trim($match[6]);
	
    if ($escaped == 'true') $code = htmlspecialchars_decode($code);
 
    $geshi = new GeSHi($code, $language);
    $geshi->enable_keyword_links(false);
	
    do_action_ref_array('wp_syntax_init_geshi', array(&$geshi));
 
    if ( ! empty($match[4]) )
    {
        $linespecs = strpos($match[4], ",") == false ? array($match[4]) : explode(",", $match[4]);
		$lines = array();
  
	foreach ($linespecs as $spec)
 	{
		$range = explode("-", $spec);
        $lines = array_merge($lines, (count($range) == 2) ? range($range[0], $range[1]) : $range);
 	}
        $geshi->highlight_lines_extra($lines);
    }
	
	$output = "\n" . '<div class="wp_syntax">';
    $output .= '<table>';

    if ( ! empty($caption) )
    {
		$output .= '<caption>' . $caption . '</caption>';
    }

    $output .= '<tr>';

    if ($line)
    {
        $output .='<td class="line_numbers">' . wp_syntax_line_numbers($code, $line) . '</td>';
    }

    $output .= '<td class="code">';
    $output .= $geshi->parse_code();
    $output .= '</td></tr></table>';
    $output .= '</div>' . "\n";

    return $output; 
}

function wp_syntax_before_filter($content)
{
    return preg_replace_callback(
        "/\s*<pre(?:lang=[\"']([\w-]+)[\"']|line=[\"'](\d*)[\"']|escaped=[\"'](true|false)?[\"']|highlight=[\"']((?:\d+[,-])*\d+)[\"']|src=[\"']([^\"']+)[\"']|\s)+>(.*)<\/pre>\s*/siU",
        "wp_syntax_substitute",
        $content
    );
}

function wp_syntax_after_filter($content)
{
    global $wp_syntax_token;

     $content = preg_replace_callback(
         '/<p>\s*' . $wp_syntax_token . '(\d{3})\s*<\/p>/si',
         'wp_syntax_highlight',
         $content
     );

    return $content;
}

$wp_syntax_token = md5(uniqid(rand()));
$wp_syntax_matches = array();

// Add styling
add_action('wp_print_styles', 'wp_syntax_style');

// Enqueue the JS
//add_action('wp_print_scripts', 'wp_syntax_scripts');

// Update config for WYSIWYG editor
add_filter('tiny_mce_before_init', 'wp_syntax_change_mce_options');

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