<?php
/**
 * Plugin Name: Category Show
 * Plugin URI: http://felipetonello.com/blog/wordpress-plugins/category-show/
 * Description: Shows all posts from a category/tag into a page/post with order support. <strong>Go to Category Show's Options page to automatically generate your tag.</strong>
 * Version: 0.4.2
 * Author: Felipe Ferreri Tonello
 * Author URI: http://felipetonello.com
 */

/*	Copyright 2009,2011	Felipe Ferreri Tonello	<eu@felipetonello.com>

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/** TODO for 0.5:
 *   - Verificar se existe alguma função no WP que retorna numero de post para cada tag or categoria. Se não, cria-la.
 *   - Adicionar na syntaxe opção para numero de resultado por página.
 *   - Função usando jQuery para lidar com as páginas
 *   - Função que seleciona resultados, por uma query SQL, da página atual.
 */

/**
 * Main Category Show function
 * @param string $content All the post content.
 * @return string
 */
function wpcs_main($content){
	global $wpdb; // wp database object
	// pattern expression updated and more secure:
	// %%wpcs-EXPRESSION%%[[-]date or title%%][id%%]
	$expre = '/%%wpcs-([\w\d-]+)%%((-)?(title|date)%%)?(id%%)?/m';

	if(preg_match_all($expre, $content, $match)){
		// how many times it appears
		foreach($match[1] as $key => $value){		
			// if $match[5][$key], which is the id%% tag, exists than select the ID not the SLUG
			$id_or_slug = (!empty($match[5][$key])) ? "term_id" : "slug";
			if($res = $wpdb->get_row("SELECT te.term_id, te.name, ta.description
									FROM $wpdb->terms te
									INNER JOIN $wpdb->term_taxonomy ta ON ta.term_id = te.term_id
									WHERE te.$id_or_slug = '$value'
									AND (ta.taxonomy = 'category'
									OR ta.taxonomy = 'post_tag')")){
				
				// How it's gonna be ordered?
				// if the tag contains 'date' or 'title', use it, otherwise use date as default
				// if there is an '-' to specify DESC, otherwise use ASC as default
				if(empty($match[4][$key])) {
					$order_name = 'po.post_date';
					$order_type = 'DESC';
				} else {
					$order_name = 'po.post_'.$match[4][$key];
					if(!empty($match[3][$key]))
						$order_type = 'DESC';
					else
						$order_type = 'ASC';
				}

				if($cat2post = $wpdb->get_results("SELECT DISTINCT po.ID, po.post_title, po.guid
												 FROM $wpdb->posts po
												 INNER JOIN $wpdb->term_relationships re ON re.object_id = po.ID
												 INNER JOIN $wpdb->term_taxonomy ta ON ta.term_taxonomy_id = re.term_taxonomy_id
												 WHERE ta.term_id = $res->term_id
												 AND po.post_status = 'publish'
												 AND po.post_type = 'post'
												 ORDER BY $order_name $order_type")){
					$html = array(); // setting an empty array
					$html[] = "<h3>$res->name</h3>";
					if(!empty($res->description))
						$html[] = "<blockquote>$res->description</blockquote>";
					$html[] = '<ul>';
					foreach ($cat2post as $post)
						$html[] = "\t<li><a href=\"$post->guid\" rel=\"bookmark\" title=\"$post->post_title\">$post->post_title</a></li>";
					$html[] = '</ul>';
					
					// I'm not using str_replace because the $count parameter doesn't work as limiter.
					// BTW, this method of limiting the searches sux a lot! As a workaround I'm using preg_replace
					// for more information about limiting the results check it out:
					// http://bugs.php.net/bug.php?id=11457
					
					$content = preg_replace('/'.$match[0][$key].'/', implode("\n", $html), $content, 1);
				}else{
					//if the category or tag exists but there is no post published in it
					$content = preg_replace('/'.$match[0][$key].'/', sprintf(__("Error(Plugin Category Show): no post published in '%s'", 'wp-category-show'), $res->name), $content, 1);
				}
			}else{
				//if category or tag doesn't exist at all
				$content = preg_replace('/'.$match[0][$key].'/', sprintf(__("Error(Plugin Category Show): %s '%s' it doesn't exist", 'wp-category-show'), $id_or_slug, $value), $content, 1);
			}
		} // end foreach
	}//end if

	return $content;
}

/**
 * Builds the options page for Category Show. This page also calls a AJAX routine to select the respective category slug
 */
function wpcs_options_page(){	
	// listing all categories and tags in the same <select> separating by groups
	$cat_tag_list = array();
	$cat_fmt = wp_dropdown_categories(array('echo' => false, 
											'name' => 'wpcs_cat_id', 
											'hide_empty' => false, 
											'orderby' => 'name', 
											'hierarchical' => true));
	
	$cat_tag_list[] = '<select id="wpcs_term_dropdown" name="wpcs_term_dropdown" class="postform">';
	$cat_tag_list[] = '<optgroup label="'.__('Categories', 'wp-category-show').'">';
	// this is a workararound to make tags and categories work togeather in the same <select>
	// it works like this: substr starts to select a string after the <select ..> declaration and stops before </select>
	$cat_tag_list[] = substr($cat_fmt, (63 - 1), (strlen($cat_fmt) - 9 - 63));
	$cat_tag_list[] = '</optgroup>';
	
	$cat_tag_list[] = '<optgroup label="'.__('Tags', 'wp-category-show').'">';
	$tags = get_terms('post_tag', array('hide_empty' => false));
	foreach($tags as $tag){
		$cat_tag_list[] = '<option value="'.$tag->term_id.'">'.$tag->name.'</option>';
	}
	$cat_tag_list[] = '</optgroup>';
	$cat_tag_list[] = '</select>';
	
	echo '<h2>Category Show</h2>
	<div id="wp-category-show" class="form-wrap">
	<h3>'.__('Generate Tag', 'wp-category-show').'</h3>
	
	<div class="form-field">
		<label>'.__('Category or Tag:', 'wp-category-show').'</label>
		'.implode("\n", $cat_tag_list).'
	</div>
	<div class="form-field">
		<label>'.__('Order by:', 'wp-category-show').'</label>
		<select id="wpcs_order_by" name="wpcs_orderby">
			<option value="title">'.__('Title', 'wp-category-show').'</option>
			<option value="date" selected="selected">'.__('Date', 'wp-category-show').'</option>
		</select>
		<select id="wpcs_order_type" name="wpcs_ordertype">
		<option value="">'.__('Ascending', 'wp-category-show').'</option>
		<option value="-" selected="selected">'.__('Descending', 'wp-category-show').'</option>
		</select>
	</div>
	<p class="submit">
	<input type="button" class="button-primary" value="'.__('Generate', 'wp-category-show').'" onClick="wpcs_gen_tag()" />
	</p>
		<label>'.__('Generated tag:', 'wp-category-show').'</label>
		<input type="text" id="wpcs_gen_tag" name="wpcs_gen_tag" onClick="this.select()" size="40" />
		<p>'.__('Insert the generated tag into your post.', 'wp-category-show').'</p>
	</div>';
}

/**
 * @deprecated
 * This function is deprecated since 0.4.1
 * 
 * Selects the slug term for given category or tag id
 * Echoes data because this function is called from an AJAX request
 */
// function wpcs_get_slug_from_id() {
// 	global $wpdb;
// 	$id= isset($_POST['wpcs_term_id']) ? $_POST['wpcs_term_id'] : "0"; // from jQuery
// 	if($res = $wpdb->get_row("SELECT te.slug
// 							FROM $wpdb->terms te
// 							WHERE te.term_id = '$id'")){
// 		echo $res->slug;
// 	}else{
// 		echo '';
// 	}
// 	// exiting because it's just a callback function
// 	exit();
// }

/**
 * Adds the options menu for Category Show
 */
function wpcs_add_opt_menu(){
	$opt_page = add_options_page('Category Show - Options', 'Category Show', 10, __FILE__, "wpcs_options_page");
	add_action("admin_print_scripts-$opt_page", 'wpcs_loadjs_admin_head');
}

/**
 * Adds Category Show's javascript file, with jquery if necessary
 */
function wpcs_loadjs_admin_head() {
	wp_enqueue_script('wp-category-show', '/wp-content/plugins/'.plugin_basename(dirname(__FILE__)).'/wp-category-show.js');
	// FIXME:
	// This is not good practice, but Wordpress 2.8.6 is not sourcing the jquery lib. So I had to add it myself.
	wp_enqueue_script('wpcs_jquery', '/wp-content/plugins/'.plugin_basename(dirname(__FILE__)).'/jquery.js');
}

/**
 * Loads translations files
 */
function wpcs_load_translation_file(){
	// relative path to WP_PLUGIN_DIR where the translation files will sit
	$plugin_path = plugin_basename(dirname(__FILE__) .'/translations');
	load_plugin_textdomain('wp-category-show', '', $plugin_path);
}


// actions and filters
add_action('init', 'wpcs_load_translation_file');
// add_action('wp_ajax_wpcs_get_slug', 'wpcs_get_slug_from_id');
add_action('admin_menu', 'wpcs_add_opt_menu');
add_filter('the_content', 'wpcs_main');
?>
